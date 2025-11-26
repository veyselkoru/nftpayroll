<?php

namespace App\Jobs;

use App\Models\NftMint;
use App\Models\Payroll;
use App\Services\IpfsClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class MintPayrollNftJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $retryAfter = 10;

    /**
     * Model taşımıyoruz, sadece ID taşıyoruz (typed property hatasına çözüm)
     */
    public int $nftMintId;

    public function __construct(NftMint $nftMint)
    {
        $this->nftMintId = $nftMint->id;
    }

    public function handle(): void
    {
        // NftMint + Payroll + Employee ilişkileriyle beraber yükle
        $nftMint = NftMint::with('payroll.employee')->find($this->nftMintId);

        if (! $nftMint) {
            return;
        }

        $payroll = $nftMint->payroll;

        if (! $payroll) {
            $this->failJob($nftMint, null, 'Missing payroll relation');
            return;
        }

        // Sadece pending durumunda işleme al
        if ($nftMint->status !== 'pending') {
            return;
        }

        // encrypted_payload yoksa IPFS'e çıkmak KVKK açısından anlamsız
        if (empty($payroll->encrypted_payload)) {
            $this->failJob($nftMint, $payroll, 'Missing encrypted payroll payload');
            return;
        }

        // Cüzdan adresi (önce NftMint, yoksa employee wallet)
        $walletAddress = $nftMint->wallet_address ?: optional($payroll->employee)->wallet_address;

        // ZERO ADDRESS veya boşsa iptal
        if (
            ! $walletAddress ||
            strtolower($walletAddress) === '0x0000000000000000000000000000000000000000'
        ) {
            $this->failJob($nftMint, $payroll, 'Wallet address empty or zero address');
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 1) METADATA OLUŞTUR (KVKK SAFE)
        |--------------------------------------------------------------------------
        | BURASI ARTIK DÜZ MAAŞ VERİSİ GÖNDERMİYOR.
        | Sadece Laravel'in AES-256 ile şifrelediği payload'ı taşıyoruz.
        */
        $metadata = [
            'name'             => "Payroll NFT #{$payroll->id}",
            'description'      => "Encrypted payroll metadata for employee #{$payroll->employee_id}",
            'encrypted_payload'=> $payroll->encrypted_payload, // 🔐 ŞİFRELİ VERİ
            'version'          => '1.0',
        ];

        /*
        |--------------------------------------------------------------------------
        | 2) IPFS (Pinata) ÜZERİNDEN METADATA YÜKLE → CID AL
        |--------------------------------------------------------------------------
        */
        try {
            $json    = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            /** @var IpfsClient $ipfs */
            $ipfs    = app(IpfsClient::class);
            $ipfsCid = $ipfs->uploadString($json); // Pinata → IpfsHash

            // Hem payroll hem nft_mints tablosuna yaz
            $nftMint->update(['ipfs_cid' => $ipfsCid]);
            $payroll->update(['ipfs_cid' => $ipfsCid]);
        } catch (\Throwable $e) {
            $this->failJob($nftMint, $payroll, 'IPFS error: '.$e->getMessage());
            return;
        }

        $tokenUri = 'ipfs://' . $ipfsCid;

        /*
        |--------------------------------------------------------------------------
        | 3) NODE SCRIPT İLE MINT
        |--------------------------------------------------------------------------
        */
        $nftMint->update(['status' => 'sending']);

        try {
            $projectRoot = base_path();
            $scriptPath  = $projectRoot . '/chain-worker/mint.js';

            $env = array_merge($_ENV, [
                'CHAIN_RPC_URL'                => env('CHAIN_RPC_URL'),
                'CHAIN_PRIVATE_KEY'            => env('CHAIN_PRIVATE_KEY'),
                'PAYROLL_NFT_CONTRACT_ADDRESS' => env('PAYROLL_NFT_CONTRACT_ADDRESS'),
            ]);

            $process = new Process(
                ['node', $scriptPath, $walletAddress, $tokenUri],
                $projectRoot,
                $env
            );

            $process->run();

            if (! $process->isSuccessful()) {
                $error = $process->getErrorOutput() ?: $process->getOutput();
                Log::error('Mint failed (node)', ['error' => $error]);

                $nftMint->update([
                    'status'        => 'failed',
                    'error_message' => trim($error),
                ]);

                $payroll->update(['status' => 'mint_failed']);

                return;
            }

            $output = trim($process->getOutput());
            $lines  = preg_split("/\r\n|\n|\r/", $output);
            $txHash = trim(end($lines));   // dotenv vs. satırlar varsa, son satır hash

            // Hash formatı kontrolü
            if (! str_starts_with($txHash, '0x') || strlen($txHash) !== 66) {
                Log::warning('Unexpected tx hash format', [
                    'raw_output' => $output,
                    'parsed'     => $txHash,
                ]);

                $nftMint->update([
                    'status'        => 'failed',
                    'error_message' => 'Invalid tx hash format from node script',
                ]);

                $payroll->update(['status' => 'mint_failed']);

                return;
            }

            // Başarılı mint → tx_hash ve status
            $nftMint->update([
                'status'  => 'sent',
                'tx_hash' => $txHash,
            ]);

            $payroll->update(['status' => 'minted']);

            Log::info('Mint successful via node', [
                'nft_mint_id' => $nftMint->id,
                'tx_hash'     => $txHash,
            ]);
        } catch (\Throwable $e) {
            $this->failJob($nftMint, $payroll, 'Mint exception: '.$e->getMessage());
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 4) TRANSACTION RECEIPT → TOKEN ID ÇÖZ
        |--------------------------------------------------------------------------
        */
        try {
            $rpcUrl = env('CHAIN_RPC_URL');

            if (! $rpcUrl) {
                throw new \Exception('CHAIN_RPC_URL not set');
            }

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($rpcUrl, [
                'jsonrpc' => '2.0',
                'method'  => 'eth_getTransactionReceipt',
                'params'  => [$txHash],
                'id'      => 1,
            ]);

            if (! $response->successful()) {
                throw new \Exception('RPC request failed: '.$response->status());
            }

            $result = $response->json('result');

            if (! $result || empty($result['logs'])) {
                throw new \Exception('No logs in receipt');
            }

            $contractAddress = strtolower(env('PAYROLL_NFT_CONTRACT_ADDRESS'));
            $transferSig     = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';

            $tokenId = null;

            foreach ($result['logs'] as $log) {
                // Sadece bizim kontrat
                if (strtolower($log['address'] ?? '') !== $contractAddress) {
                    continue;
                }

                // Transfer event
                if (! isset($log['topics'][0]) || strtolower($log['topics'][0]) !== $transferSig) {
                    continue;
                }

                // tokenId topic[3]
                if (! isset($log['topics'][3])) {
                    continue;
                }

                $tokenIdHex = $log['topics'][3];
                $tokenId    = hexdec($tokenIdHex);
                break;
            }

            if ($tokenId === null) {
                throw new \Exception('Transfer event not found for contract');
            }

            $nftMint->update([
                'token_id' => $tokenId,
            ]);

            Log::info('Token ID resolved', [
                'nft_mint_id' => $nftMint->id,
                'tx_hash'     => $txHash,
                'token_id'    => $tokenId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Token ID resolve failed', [
                'nft_mint_id' => $nftMint->id,
                'tx_hash'     => $txHash ?? null,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    private function failJob(NftMint $nftMint, ?Payroll $payroll, string $msg): void
    {
        $nftMint->update([
            'status'        => 'failed',
            'error_message' => $msg,
        ]);

        if ($payroll) {
            $payroll->update(['status' => 'mint_failed']);
        }

        Log::error('Mint job failed', [
            'nft_mint_id' => $nftMint->id,
            'message'     => $msg,
        ]);
    }
}
