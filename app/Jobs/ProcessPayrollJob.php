<?php

namespace App\Jobs;

use App\Models\Payroll;
use App\Models\NftMint;
use App\Jobs\MintPayrollNftJob;
use App\Services\IpfsClient;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;




class ProcessPayrollJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Payroll $payroll;

    public function __construct(Payroll $payroll)
    {
        $this->payroll = $payroll;
    }

    public function handle(IpfsClient $ipfsClient): void
    {
        Log::info("Processing payroll", [
            'payroll_id' => $this->payroll->id,
        ]);

        // 1) IPFS yoksa önce IPFS'e yükle
        if (! $this->payroll->ipfs_cid) {
            if (! $this->payroll->encrypted_payload) {
                Log::warning('Payroll has no encrypted payload', [
                    'payroll_id' => $this->payroll->id,
                ]);
                return;
            }

            $content = $this->payroll->encrypted_payload;
            $cid     = $ipfsClient->uploadString($content);

            $this->payroll->update([
                'ipfs_cid' => $cid,
                'status'   => 'ipfs_uploaded',
            ]);

            Log::info('Payroll uploaded to IPFS', [
                'payroll_id' => $this->payroll->id,
                'ipfs_cid'   => $cid,
            ]);
        } else {
            $cid = $this->payroll->ipfs_cid;
            Log::info('Payroll already has IPFS CID', [
                'payroll_id' => $this->payroll->id,
                'ipfs_cid'   => $cid,
            ]);
        }

        // 2) Buradan sonrası: her durumda mint işini kontrol et

        $employee      = $this->payroll->employee;
        $walletAddress = $employee?->wallet_address;

        if (! $walletAddress) {
            Log::warning('Employee has no wallet address, skip mint', [
                'payroll_id' => $this->payroll->id,
            ]);
            return;
        }

        // Zaten bir NftMint kaydı var mı?
        $nftMint = $this->payroll->nftMint;

        if (! $nftMint) {
            // İlk defa oluştur
            $nftMint = NftMint::create([
                'payroll_id'     => $this->payroll->id,
                'wallet_address' => $walletAddress,
                'ipfs_cid'       => $cid,
                'status'         => 'pending',
            ]);
        } else {
            // Kayıt var; status failed/pending ise tekrar denemeye izin ver
            if (! in_array($nftMint->status, ['pending', 'success'])) {
                Log::info('NftMint already processed, skipping mint job', [
                    'nft_mint_id' => $nftMint->id,
                    'status'      => $nftMint->status,
                ]);
                return;
            }
        }

        // Mint job'u her iki durumda da kuyruğa at
        MintPayrollNftJob::dispatch($nftMint);

        $this->payroll->update([
            'status' => 'mint_queued',
        ]);

        Log::info('Mint job queued', [
            'payroll_id' => $this->payroll->id,
            'nft_mint_id'=> $nftMint->id,
        ]);
    }
}
