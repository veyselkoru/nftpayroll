<?php

namespace Tests\Feature;

use App\Events\Workflow\MintFailed;
use App\Events\Workflow\MintStarted;
use App\Events\Workflow\MintSucceeded;
use App\Models\Company;
use App\Models\Employee;
use App\Models\NftMint;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WorkflowMintLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_payroll_flow_writes_admin_modules_from_workflow_events(): void
    {
        Queue::fake();

        $owner = User::factory()->create(['role' => User::ROLE_COMPANY_OWNER]);
        $company = Company::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Workflow Co',
        ]);
        $owner->update(['company_id' => $company->id]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'name' => 'Alice',
            'surname' => 'Jones',
            'national_id' => '12345678901',
            'wallet_address' => '0x1111111111111111111111111111111111111111',
            'status' => 'active',
        ]);

        $payrollPayload = [
            'national_id' => '12345678901',
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'payment_date' => '2026-02-05',
            'currency' => 'TRY',
            'gross_salary' => 100000,
            'net_salary' => 80000,
            'bonus' => 5000,
            'deductions_total' => 3000,
        ];

        $this->actingAs($owner)
            ->postJson("/api/companies/{$company->id}/employees/{$employee->id}/payrolls", $payrollPayload)
            ->assertCreated();

        $payroll = Payroll::query()->latest('id')->firstOrFail();
        $nftMint = NftMint::query()->where('payroll_id', $payroll->id)->firstOrFail();

        $this->assertDatabaseHas('operation_jobs', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'payroll_id' => $payroll->id,
            'nft_mint_id' => $nftMint->id,
            'status' => 'queued',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'module' => 'payroll',
            'action' => 'created',
        ]);

        event(new MintStarted(
            companyId: (int) $company->id,
            employeeId: (int) $employee->id,
            payrollId: (int) $payroll->id,
            nftMintId: (int) $nftMint->id,
        ));

        $nftMint->update([
            'status' => 'sent',
            'tx_hash' => '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'token_id' => 77,
            'network' => 'sepolia',
            'gas_used' => 21000,
            'gas_fee_eth' => 0.00042,
            'gas_fee_fiat' => 1.25,
            'cost_source' => 'onchain_receipt',
        ]);

        event(new MintSucceeded(
            companyId: (int) $company->id,
            employeeId: (int) $employee->id,
            payrollId: (int) $payroll->id,
            nftMintId: (int) $nftMint->id,
            txHash: $nftMint->tx_hash,
            tokenId: $nftMint->token_id,
            durationMs: 1200,
        ));

        $this->assertDatabaseHas('operation_jobs', [
            'nft_mint_id' => $nftMint->id,
            'status' => 'completed',
            'tx_hash' => $nftMint->tx_hash,
            'token_id' => 77,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'module' => 'mint',
            'action' => 'succeeded',
        ]);

        $this->actingAs($owner)
            ->getJson('/api/cost-reports/summary?from=2026-01-01&to=2026-12-31')
            ->assertOk()
            ->assertJsonPath('data.total_mints', 1)
            ->assertJsonPath('data.total_gas_used', 21000);

        $failedPayroll = Payroll::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
            'currency' => 'TRY',
            'gross_salary' => 100000,
            'net_salary' => 80000,
            'status' => 'queued',
            'encrypted_payload' => encrypt(['x' => 1]),
        ]);
        $failedMint = NftMint::query()->create([
            'payroll_id' => $failedPayroll->id,
            'wallet_address' => '0x1111111111111111111111111111111111111111',
            'ipfs_cid' => 'bafybeigdyrztz',
            'status' => 'pending',
        ]);

        event(new MintFailed(
            companyId: (int) $company->id,
            employeeId: (int) $employee->id,
            payrollId: (int) $failedPayroll->id,
            nftMintId: (int) $failedMint->id,
            errorMessage: 'RPC timeout',
        ));

        $this->assertDatabaseHas('notification_events', [
            'company_id' => $company->id,
            'title' => 'Mint failed',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'module' => 'mint',
            'action' => 'failed',
        ]);
    }
}

