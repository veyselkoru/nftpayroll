<?php

namespace Tests\Feature;

use App\Events\Workflow\MintFailed;
use App\Events\Workflow\MintSucceeded;
use App\Models\Company;
use App\Models\Employee;
use App\Models\NftMint;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MintStatusEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_sets_pending_and_status_endpoint_returns_pending(): void
    {
        Queue::fake();
        [$owner, $company, $employee, $payroll] = $this->seedContext();

        $this->actingAs($owner)
            ->postJson("/api/companies/{$company->id}/employees/{$employee->id}/payrolls/{$payroll->id}/queue")
            ->assertOk();

        $nftMint = NftMint::query()->where('payroll_id', $payroll->id)->firstOrFail();
        $this->assertSame('pending', $nftMint->status);

        $this->actingAs($owner)
            ->getJson("/api/companies/{$company->id}/employees/{$employee->id}/payrolls/{$payroll->id}/status")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_worker_success_simulation_sets_sent_and_token_id(): void
    {
        Queue::fake();
        [$owner, $company, $employee, $payroll] = $this->seedContext();

        $this->actingAs($owner)
            ->postJson("/api/companies/{$company->id}/employees/{$employee->id}/payrolls/{$payroll->id}/queue")
            ->assertOk();

        $nftMint = NftMint::query()->where('payroll_id', $payroll->id)->firstOrFail();
        event(new MintSucceeded(
            companyId: (int) $company->id,
            employeeId: (int) $employee->id,
            payrollId: (int) $payroll->id,
            nftMintId: (int) $nftMint->id,
            txHash: '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
            tokenId: 125,
            durationMs: 900,
        ));

        $payroll->refresh();
        $nftMint->refresh();

        $this->assertSame('sent', $payroll->status);
        $this->assertSame('sent', $nftMint->status);
        $this->assertSame(125, (int) $nftMint->token_id);

        $this->actingAs($owner)
            ->getJson("/api/companies/{$company->id}/employees/{$employee->id}/payrolls/{$payroll->id}/status")
            ->assertOk()
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.token_id', 125);
    }

    public function test_failed_event_sets_failed_and_error_message(): void
    {
        Queue::fake();
        [$owner, $company, $employee, $payroll] = $this->seedContext();

        $this->actingAs($owner)
            ->postJson("/api/companies/{$company->id}/employees/{$employee->id}/payrolls/{$payroll->id}/queue")
            ->assertOk();

        $nftMint = NftMint::query()->where('payroll_id', $payroll->id)->firstOrFail();
        event(new MintFailed(
            companyId: (int) $company->id,
            employeeId: (int) $employee->id,
            payrollId: (int) $payroll->id,
            nftMintId: (int) $nftMint->id,
            errorMessage: 'Mint RPC failed',
        ));

        $payroll->refresh();
        $nftMint->refresh();

        $this->assertSame('failed', $payroll->status);
        $this->assertSame('failed', $nftMint->status);
        $this->assertSame('Mint RPC failed', $nftMint->error_message);
    }

    public function test_status_endpoint_returns_expected_shape(): void
    {
        Queue::fake();
        [$owner, $company, $employee, $payroll] = $this->seedContext();

        $this->actingAs($owner)
            ->postJson("/api/companies/{$company->id}/employees/{$employee->id}/payrolls/{$payroll->id}/queue")
            ->assertOk();

        $this->actingAs($owner)
            ->getJson("/api/companies/{$company->id}/employees/{$employee->id}/payrolls/{$payroll->id}/status")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'payroll_id',
                    'status',
                    'token_id',
                    'tx_hash',
                    'ipfs_cid',
                    'updated_at',
                    'nft' => [
                        'status',
                        'token_id',
                        'tx_hash',
                        'ipfs_cid',
                        'image_url',
                    ],
                ],
            ]);
    }

    private function seedContext(): array
    {
        $owner = User::factory()->create(['role' => User::ROLE_COMPANY_OWNER]);
        $company = Company::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Status Co',
        ]);
        $owner->update(['company_id' => $company->id]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'name' => 'John',
            'surname' => 'Doe',
            'national_id' => '12345678901',
            'wallet_address' => '0x1111111111111111111111111111111111111111',
            'status' => 'active',
        ]);

        $payroll = Payroll::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'payment_date' => '2026-02-10',
            'currency' => 'TRY',
            'gross_salary' => 90000,
            'net_salary' => 70000,
            'status' => 'pending',
            'encrypted_payload' => encrypt(['ok' => true]),
        ]);

        return [$owner, $company, $employee, $payroll];
    }
}

