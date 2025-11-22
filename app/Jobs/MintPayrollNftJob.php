<?php

namespace App\Jobs;

use App\Models\NftMint;
use App\Models\Payroll;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Http;

class MintPayrollNftJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $retryAfter = 10;

    // 🔴 ARTIK MODEL DEĞİL, SADECE ID TUTUYORUZ
    public int $nftMintId;

    public function __construct(NftMint $nftMint)
    {
        $this->nftMintId = $nftMint->id;
    }

    public function handle(): void
    {
        // 🔴 MODELİ BURADA TEKRAR ÇEK
        $nftMint = NftMint::with('payroll.employee')->find($this->nftMintId);

        if (! $nftMint) {
            return;
        }

        $payroll = $nftMint->payroll;

        if (! $payroll) {
            $this->failJob($nftMint, null, 'Missing payroll relation');
            return;
        }

        // sadece pending ise işleme al
        if ($nftMint->status !== 'pending') {
            return;
        }

        $walletAddress = $nftMint->wallet_address ?: $payroll->employee->wallet_address;

        if (! $walletAddress || strtolower($walletAddress) === '0x0000000000000000000000000000000000000000') {
            $this->failJob($nftMint, $payroll, 'Wallet address empty or zero address');
            return;
        }

        // processing
        $nftMint->update(['status' => 'processing']);

        // Basit metadata örneği
        $metadata = [
            'name'        => "Payroll NFT #{$payroll->id}",
            'description' => "Payroll token for employee #{$payroll->employee_id}",
            'data' => [
                'period_start' => $payroll->period_start,
                'period_end'   => $payroll->period_end,
                'gross_salary' => $payroll->gross_salary,
                'net_salary'   => $payroll->net_salary,
            ],
        ];

        // --- IPFS CID (şimdilik fake, sonra gerçek servise bağlarız) ---
        try {
            $ipfsCid = 'bafy' . substr(sha1(json_encode($metadata)), 0, 20);

            $nftMint->update(['ipfs_cid' => $ipfsCid]);
            $payroll->update(['ipfs_cid' => $ipfsCid]);
        } catch (\Throwable $e) {
            $this->failJob($nftMint, $payroll, 'IPFS error: '.$e->getMessage());
            return;
        }

        // --- Node mint.js çağrısı ---
        try {
            $tokenUri = 'ipfs://' . $ipfsCid;

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
                $this->failJob($nftMint, $payroll, 'Mint failed (node): '.trim($error));
                return;
            }

            $output = trim($process->getOutput());
            $lines  = preg_split("/\r\n|\n|\r/", $output);
            $txHash = trim(end($lines));

            if (! str_starts_with($txHash, '0x') || strlen($txHash) !== 66) {
                $this->failJob($nftMint, $payroll, 'Invalid tx hash format from node script');
                return;
            }

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

        // --- Tx receipt → tokenId çöz ---
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
                if (strtolower($log['address'] ?? '') !== $contractAddress) {
                    continue;
                }

                if (! isset($log['topics'][0]) || strtolower($log['topics'][0]) !== $transferSig) {
                    continue;
                }

                if (! isset($log['topics'][3])) {
                    continue;
                }

                $tokenIdHex = $log['topics'][3];
                $tokenId    = hexdec($tokenIdHex);

                break;
            }

            if ($tokenId !== null) {
                $nftMint->update([
                    'token_id' => $tokenId,
                ]);

                Log::info('Token ID resolved', [
                    'nft_mint_id' => $nftMint->id,
                    'tx_hash'     => $txHash,
                    'token_id'    => $tokenId,
                ]);
            }

        } catch (\Throwable $e) {
            Log::warning('Token ID resolve failed', [
                'nft_mint_id' => $nftMint->id,
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
