<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\Workflow\MintQueued;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\NftMint;
use App\Models\Admin\ApprovalRequest;
use App\Models\Admin\NotificationEvent;
use App\Models\Admin\WalletValidation;
use App\Jobs\MintPayrollNftJob;
use Illuminate\Http\Request;

class PayrollQueueController extends Controller
{
    use \App\Http\Controllers\Api\Concerns\AuthorizesCompany;

    public function queue(
        Request $request,
        Company $company,
        Employee $employee,
        Payroll $payroll
    ) {
        // 1) Şirket erişimini ve employee-company ilişkisini doğrula
        $this->authorizeCompany($request->user(), $company);

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

        $walletAddress = $employee->wallet_address ?: $this->defaultMintWalletAddress();
        $isValidWallet = (bool) preg_match('/^0x[0-9a-fA-F]{40}$/', (string) $walletAddress);

        WalletValidation::create([
            'company_id' => $company->id,
            'user_id' => $request->user()->id,
            'wallet_address' => (string) $walletAddress,
            'network' => 'sepolia',
            'status' => $isValidWallet ? 'valid' : 'invalid',
            'message' => $isValidWallet ? 'Wallet validated before queue' : 'Invalid wallet before queue',
            'checked_at' => now(),
        ]);

        if (! $isValidWallet && ! filter_var(env('WALLET_POLICY_OVERRIDE', false), FILTER_VALIDATE_BOOL)) {
            NotificationEvent::create([
                'company_id' => $company->id,
                'user_id' => $request->user()->id,
                'title' => 'Invalid wallet blocked mint queue',
                'body' => 'Wallet validation failed before queue',
                'channel' => 'in_app',
                'status' => 'queued',
                'is_read' => false,
                'payload' => ['employee_id' => $employee->id, 'payroll_id' => $payroll->id],
            ]);

            return response()->json([
                'message' => 'Wallet geçersiz, queue işlemi bloklandı.',
            ], 422);
        }

        if (filter_var(env('MINT_APPROVAL_REQUIRED', false), FILTER_VALIDATE_BOOL)) {
            $approval = ApprovalRequest::query()
                ->where('payroll_id', $payroll->id)
                ->where('type', 'mint_approval')
                ->latest('id')
                ->first();

            if (! $approval) {
                ApprovalRequest::create([
                    'company_id' => $company->id,
                    'employee_id' => $employee->id,
                    'payroll_id' => $payroll->id,
                    'user_id' => $request->user()->id,
                    'title' => 'Mint approval required',
                    'type' => 'mint_approval',
                    'policy_key' => 'mint.approval.required',
                    'status' => 'pending',
                    'payload' => ['payroll_id' => $payroll->id, 'employee_id' => $employee->id],
                ]);
            }

            if (! $approval || $approval->status !== 'approved') {
                $payroll->update(['status' => 'awaiting_approval']);
                return response()->json([
                    'message' => 'Mint işlemi onay bekliyor.',
                ], 422);
            }
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
                'company_id' => $company->id,
                'wallet_address' => $walletAddress,
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

        event(new MintQueued(
            companyId: (int) $company->id,
            employeeId: (int) $employee->id,
            payrollId: (int) $payroll->id,
            nftMintId: (int) $nftMint->id,
            triggeredByUserId: $request->user()->id,
        ));

        return response()->json([
            'message'     => 'Mint kuyruğa alındı.',
            'nft_mint_id' => $nftMint->id,
            'status'      => $nftMint->status,
        ], 200);
    }

    protected function defaultMintWalletAddress(): string
    {
        return (string) env('DEFAULT_MINT_WALLET', '0x125E82e69A4b499315806b10b9678f3CDE6B977E');
    }
}
