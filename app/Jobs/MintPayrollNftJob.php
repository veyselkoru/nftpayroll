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

    /**
     * Queue retry ayarları
     */
    public int $tries = 5;
    public int $retryAfter = 10;

    /**
     * Model taşımıyoruz, sadece ID taşıyoruz (typed property hatası için)
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
            Log::warning('Mint job: NftMint not found', [
                'nft_mint_id' => $this->nftMintId,
            ]);
            return;
        }

        $payroll = $nftMint->payroll;

        if (! $payroll) {
            $this->failJob($nftMint, null, 'Missing payroll relation');
            return;
        }

        // Sadece pending durumunda işleme al
        if ($nftMint->status !== 'pending') {
            Log::info('Mint job skipped, status is not pending', [
                'nft_mint_id' => $nftMint->id,
                'status'      => $nftMint->status,
            ]);
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
        | Sadece Laravel'in AES-256 ile şifrelediği payload'ı taşıyoruz.
        */

        $imageCid = env('NFTPAYROLL_IMAGE_CID');

        if (! $imageCid) {
            $this->failJob($nftMint, $payroll, 'NFTPAYROLL_IMAGE_CID env not set');
            return;
        }

        $metadata = [
            'name'              => "Payroll NFT #{$payroll->id}",
            'description'       => "Encrypted payroll metadata for employee #{$payroll->employee_id}",
            'encrypted_payload' => $payroll->encrypted_payload, // 🔐 şifreli veri
            'version'           => '1.0',
            // MetaMask için https URL (ipfs:// yerine)
            'image'             => "https://ipfs.io/ipfs/{$imageCid}",
        ];

        /*
        |--------------------------------------------------------------------------
        | 2) IPFS (Pinata) ÜZERİNDEN METADATA YÜKLE → CID AL
        |--------------------------------------------------------------------------
        */
        try {
            /** @var IpfsClient $ipfs */
            $ipfs = app(IpfsClient::class);

            // uploadJson ARRAY bekliyor, json_encode etmiyoruz
            $ipfsCid = $ipfs->uploadJson($metadata);

            if (! $ipfsCid || strlen($ipfsCid) < 20) {
                throw new \Exception('Invalid IPFS CID returned: ' . print_r($ipfsCid, true));
            }

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

                Log::error('Mint failed (node)', [
                    'nft_mint_id' => $nftMint->id,
                    'error'       => $error,
                ]);

                $nftMint->update([
                    'status'        => 'failed',
                    'error_message' => trim($error),
                ]);

                $payroll->update(['status' => 'mint_failed']);

                return;
            }

            // ÇIKTI → TX HASH PARSE
            $output = $process->getOutput() . "\n" . $process->getErrorOutput();

            // Sadece 0x + 64 hex karakter olan patterni yakala
            preg_match('/0x[a-fA-F0-9]{64}/', $output, $matches);
            $txHash = $matches[0] ?? null;

            if (! $txHash) {
                Log::warning('TX hash not found in node output', [
                    'nft_mint_id' => $nftMint->id,
                    'raw_output'  => $output,
                ]);

                $nftMint->update([
                    'status'        => 'failed',
                    'error_message' => 'Invalid or missing tx hash from node script',
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

            $contractAddress = strtolower(env('PAYROLL_NFT_CONTRACT_ADDRESS'));
            if (! $contractAddress) {
                throw new \Exception('PAYROLL_NFT_CONTRACT_ADDRESS not set');
            }

            $transferSig = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';

            $tokenId  = null;
            $attempts = 0;

            while ($attempts < 3 && $tokenId === null) {
                // Biraz bekle, node full receipt'i yazsın
                usleep(800000); // 0.8 saniye

                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])->post($rpcUrl, [
                    'jsonrpc' => '2.0',
                    'method'  => 'eth_getTransactionReceipt',
                    'params'  => [$txHash],
                    'id'      => 1,
                ]);

                if (! $response->successful()) {
                    Log::warning('RPC request not successful when resolving token ID', [
                        'nft_mint_id' => $nftMint->id,
                        'status'      => $response->status(),
                        'body'        => $response->body(),
                    ]);
                    $attempts++;
                    continue;
                }

                $result = $response->json('result');

                if (! $result || empty($result['logs'])) {
                    Log::info('No logs in receipt yet, retrying...', [
                        'nft_mint_id' => $nftMint->id,
                        'attempt'     => $attempts + 1,
                    ]);
                    $attempts++;
                    continue;
                }

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

                $attempts++;
            }

            if ($tokenId === null) {
                Log::warning('Token ID resolve failed after retries', [
                    'nft_mint_id' => $nftMint->id,
                    'tx_hash'     => $txHash,
                ]);
                // Burada job'u fail etmiyoruz, sadece token_id boş kalır
                return;
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
            Log::warning('Token ID resolve exception', [
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
            'payroll_id'  => $payroll->id ?? null,
            'message'     => $msg,
        ]);
    }
}
