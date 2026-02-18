<?php

namespace App\Http\Controllers\Api\Admin;

use App\Events\Workflow\MintQueued;
use App\Http\Resources\Admin\ApprovalRequestResource;
use App\Jobs\MintPayrollNftJob;
use App\Models\Admin\ApprovalRequest;
use App\Models\Payroll;
use App\Models\NftMint;
use Illuminate\Http\Request;

class ApprovalsController extends BaseAdminController
{
    public function index(Request $request)
    {
        $query = $this->listQuery->apply($request, $this->scopeCompany($request, ApprovalRequest::query()), ['title', 'type'], ['id', 'title', 'status', 'created_at']);
        $items = $query->paginate($this->listQuery->perPage($request));

        return $this->paginated($items, ApprovalRequestResource::class, 'Approvals listed');
    }

    public function approve(Request $request, int $id)
    {
        $approval = $this->findInCompanyOrFail($request, ApprovalRequest::class, $id);
        $approval->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
        $this->auditLog->log($request->user(), 'approvals', 'approve', $approval);

        if ($approval->type === 'mint_approval' && $approval->payroll_id) {
            $this->queueMintForApprovedPayroll($request, $approval);
        }

        return $this->ok(new ApprovalRequestResource($approval), 'Approval accepted');
    }

    public function reject(Request $request, int $id)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $approval = $this->findInCompanyOrFail($request, ApprovalRequest::class, $id);
        $approval->update(['status' => 'rejected', 'rejected_at' => now(), 'rejection_reason' => $data['reason'] ?? null]);
        $this->auditLog->log($request->user(), 'approvals', 'reject', $approval, $data);

        return $this->ok(new ApprovalRequestResource($approval), 'Approval rejected');
    }

    public function metrics(Request $request)
    {
        $base = $this->scopeCompany($request, ApprovalRequest::query());

        return $this->ok([
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'rejected' => (clone $base)->where('status', 'rejected')->count(),
        ], 'Approval metrics');
    }

    protected function queueMintForApprovedPayroll(Request $request, ApprovalRequest $approval): void
    {
        /** @var Payroll|null $payroll */
        $payroll = Payroll::query()->with('employee', 'nftMint')->find($approval->payroll_id);
        if (! $payroll || ! $payroll->employee) {
            return;
        }

        $nftMint = $payroll->nftMint;
        $walletAddress = $payroll->employee->wallet_address ?: (string) env('DEFAULT_MINT_WALLET', '0x125E82e69A4b499315806b10b9678f3CDE6B977E');

        if ($nftMint && in_array($nftMint->status, ['pending', 'processing', 'sending', 'sent'], true)) {
            return;
        }

        if (! $nftMint) {
            $nftMint = NftMint::create([
                'payroll_id' => $payroll->id,
                'company_id' => $payroll->company_id,
                'wallet_address' => $walletAddress,
                'status' => 'pending',
                'error_message' => null,
            ]);
        } else {
            $nftMint->update([
                'status' => 'pending',
                'error_message' => null,
                'tx_hash' => null,
                'token_id' => null,
            ]);
        }

        $payroll->update(['status' => 'queued']);
        MintPayrollNftJob::dispatch($nftMint);

        event(new MintQueued(
            companyId: (int) $payroll->company_id,
            employeeId: (int) $payroll->employee_id,
            payrollId: (int) $payroll->id,
            nftMintId: (int) $nftMint->id,
            triggeredByUserId: $request->user()->id,
        ));
    }
}
