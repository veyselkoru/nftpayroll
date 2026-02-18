<?php

namespace Tests\Feature;

use App\Models\Admin\ApprovalRequest;
use App\Models\Admin\AuditLog;
use App\Models\Admin\BulkOperationRun;
use App\Models\Admin\ExportJob;
use App\Models\Admin\IntegrationConnection;
use App\Models\Admin\NotificationEvent;
use App\Models\Admin\OperationJob;
use App\Models\Admin\RoleDefinition;
use App\Models\Admin\SystemHealthSnapshot;
use App\Models\Admin\TemplateDefinition;
use App\Models\Admin\WalletValidation;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminModulesApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => User::ROLE_COMPANY_OWNER]);
        $company = Company::create(['owner_id' => $this->user->id, 'name' => 'Demo Co']);
        $this->user->update(['company_id' => $company->id]);
    }

    public function test_operations_module_index_and_write(): void
    {
        $job = OperationJob::factory()->create(['company_id' => $this->user->company_id, 'user_id' => $this->user->id]);

        $this->actingAs($this->user)->getJson('/api/operations/jobs')->assertOk()->assertJsonStructure(['data', 'meta', 'message']);
        $this->actingAs($this->user)->postJson('/api/operations/jobs/'.$job->id.'/retry')->assertOk();
    }

    public function test_approvals_module_index_and_write(): void
    {
        $approval = ApprovalRequest::factory()->create(['company_id' => $this->user->company_id, 'user_id' => $this->user->id]);

        $this->actingAs($this->user)->getJson('/api/approvals')->assertOk()->assertJsonStructure(['data', 'meta', 'message']);
        $this->actingAs($this->user)->postJson('/api/approvals/'.$approval->id.'/approve')->assertOk();
    }

    public function test_compliance_module_index_and_export_history(): void
    {
        AuditLog::factory()->create(['company_id' => $this->user->company_id, 'user_id' => $this->user->id]);
        ExportJob::factory()->create(['company_id' => $this->user->company_id, 'user_id' => $this->user->id]);

        $this->actingAs($this->user)->getJson('/api/compliance/audit-logs')->assertOk()->assertJsonStructure(['data', 'meta', 'message']);
        $this->actingAs($this->user)->getJson('/api/compliance/export-history')->assertOk();
    }

    public function test_notifications_module_index_and_write(): void
    {
        $event = NotificationEvent::factory()->create(['company_id' => $this->user->company_id, 'user_id' => $this->user->id]);

        $this->actingAs($this->user)->getJson('/api/notifications')->assertOk()->assertJsonStructure(['data', 'meta', 'message']);
        $this->actingAs($this->user)->postJson('/api/notifications/'.$event->id.'/read')->assertOk();
    }

    public function test_integrations_module_index_and_write(): void
    {
        $integration = IntegrationConnection::factory()->create(['company_id' => $this->user->company_id, 'user_id' => $this->user->id]);

        $this->actingAs($this->user)->getJson('/api/integrations')->assertOk()->assertJsonStructure(['data', 'meta', 'message']);
        $this->actingAs($this->user)->postJson('/api/integrations/'.$integration->id.'/test')->assertOk();
    }

    public function test_templates_module_index_and_write(): void
    {
        $template = TemplateDefinition::factory()->create(['company_id' => $this->user->company_id, 'user_id' => $this->user->id]);

        $this->actingAs($this->user)->getJson('/api/templates')->assertOk()->assertJsonStructure(['data', 'meta', 'message']);
        $this->actingAs($this->user)->postJson('/api/templates/'.$template->id.'/publish')->assertOk();
    }

    public function test_wallets_module_index_and_write(): void
    {
        WalletValidation::factory()->create(['company_id' => $this->user->company_id, 'user_id' => $this->user->id]);

        $this->actingAs($this->user)->getJson('/api/wallets')->assertOk()->assertJsonStructure(['data', 'meta', 'message']);
        $this->actingAs($this->user)->postJson('/api/wallets/validate', [
            'wallet_address' => '0x1111111111111111111111111111111111111111',
            'network' => 'sepolia',
        ])->assertOk();
    }

    public function test_bulk_operations_module_index_and_write(): void
    {
        $run = BulkOperationRun::factory()->create(['company_id' => $this->user->company_id, 'user_id' => $this->user->id]);

        $this->actingAs($this->user)->getJson('/api/bulk-operations')->assertOk()->assertJsonStructure(['data', 'meta', 'message']);
        $this->actingAs($this->user)->postJson('/api/bulk-operations/'.$run->id.'/retry')->assertOk();
    }

    public function test_cost_reports_module_summary_and_by_company(): void
    {
        ExportJob::factory()->create(['company_id' => $this->user->company_id, 'user_id' => $this->user->id]);

        $this->actingAs($this->user)->getJson('/api/cost-reports/summary')->assertOk()->assertJsonStructure(['data', 'meta', 'message']);
        $this->actingAs($this->user)->getJson('/api/cost-reports/by-company')->assertOk();
    }

    public function test_roles_module_index_and_write(): void
    {
        $role = RoleDefinition::factory()->create(['company_id' => $this->user->company_id]);

        $this->actingAs($this->user)->getJson('/api/roles')->assertOk()->assertJsonStructure(['data', 'meta', 'message']);
        $this->actingAs($this->user)->putJson('/api/roles/'.$role->id, ['name' => 'Updated Role'])->assertOk();
    }

    public function test_exports_module_index_and_write(): void
    {
        $export = ExportJob::factory()->create(['company_id' => $this->user->company_id, 'user_id' => $this->user->id]);

        $this->actingAs($this->user)->getJson('/api/exports')->assertOk()->assertJsonStructure(['data', 'meta', 'message']);
        $this->actingAs($this->user)->getJson('/api/exports/'.$export->id.'/download')->assertOk();
    }

    public function test_system_health_module_overview_and_services(): void
    {
        SystemHealthSnapshot::factory()->create();

        $this->actingAs($this->user)->getJson('/api/system-health/overview')->assertOk()->assertJsonStructure(['data', 'meta', 'message']);
        $this->actingAs($this->user)->getJson('/api/system-health/services')->assertOk();
    }

    public function test_manager_cannot_update_other_company_record_by_id(): void
    {
        $manager = User::factory()->create(['role' => User::ROLE_COMPANY_MANAGER]);
        $companyA = Company::create(['owner_id' => $this->user->id, 'name' => 'A Co']);
        $companyB = Company::create(['owner_id' => $this->user->id, 'name' => 'B Co']);
        $manager->update(['company_id' => $companyA->id]);

        $otherCompanyIntegration = IntegrationConnection::factory()->create([
            'company_id' => $companyB->id,
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($manager)
            ->putJson('/api/integrations/'.$otherCompanyIntegration->id, ['name' => 'Hacked'])
            ->assertNotFound();
    }

    public function test_employee_cannot_access_admin_module_endpoints(): void
    {
        $employee = User::factory()->create([
            'role' => User::ROLE_EMPLOYEE,
            'company_id' => $this->user->company_id,
        ]);

        $this->actingAs($employee)
            ->getJson('/api/operations/jobs')
            ->assertForbidden();
    }

    public function test_manager_cannot_use_owner_only_roles_manage_endpoint(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_COMPANY_MANAGER,
            'company_id' => $this->user->company_id,
        ]);

        $this->actingAs($manager)
            ->getJson('/api/roles')
            ->assertForbidden();
    }
}
