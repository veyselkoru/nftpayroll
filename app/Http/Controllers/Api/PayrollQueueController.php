<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\NftMint;
use App\Jobs\MintPayrollNftJob;
use Illuminate\Http\Request;

class PayrollQueueController extends Controller
{
    public function queue(
        Request $request,
        Company $company,
        Employee $employee,
        Payroll $payroll
    ) {
        // 1) Şirket sahibini ve employee-company ilişkisini doğrula
        if ($company->owner_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Yetkisiz',
            ], 403);
        }

        if ($employee->company_id !== $company->id) {
            return response()->json([
                'message' => 'Çalışan bu şirkete ait değil',
            ], 404);
        }

        if ($payroll->employee_id !== $employee->id) {
            return response()->json([
                'message' => 'Bordro bu çalışana ait değil',
            ], 404);
        }

        // 2) Bu payroll için var olan NftMint kaydını kontrol et
        $nftMint = $payroll->nftMint;

        if ($nftMint) {
            // Eğer halihazırda pending / processing / sent durumundaysa tekrar kuyruğa alma
            if (in_array($nftMint->status, ['pending', 'processing', 'sent'])) {
                return response()->json([
                    'message' => 'Bu payroll için mint süreci zaten başlatılmış.',
                    'nft_mint_id' => $nftMint->id,
                    'status'      => $nftMint->status,
                ], 422);
            }

            // failed vb. ise aşağıda resetleyip tekrar kuyruğa alacağız
        }

        // 3) NftMint kaydını oluştur veya resetle
        if (! $nftMint) {
            $nftMint = NftMint::create([
                'payroll_id' => $payroll->id,
                'wallet_address' => $employee->wallet_address,
                'status'     => 'pending',
                'error_message' => null,
                'token_id'   => null,
                'tx_hash'    => null,
                'ipfs_cid'   => $payroll->ipfs_cid, // varsa
            ]);
        } else {
            $nftMint->update([
                'status'        => 'pending',
                'error_message' => null,
                'token_id'      => null,
                'tx_hash'       => null,
            ]);
        }

        // 4) Payroll status'ini de istersen güncelleyebilirsin (opsiyonel)
        $payroll->update([
            'status' => 'queued',
        ]);

        // 5) Job'ı kuyruğa at
        MintPayrollNftJob::dispatch($nftMint);

        return response()->json([
            'message'     => 'Mint kuyruğa alındı.',
            'nft_mint_id' => $nftMint->id,
            'status'      => $nftMint->status,
        ], 200);
    }
}
