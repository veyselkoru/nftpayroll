<?php

namespace Database\Seeders;

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
use Illuminate\Database\Seeder;

class AdminModulesSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::first() ?? User::factory()->create([
            'role' => User::ROLE_COMPANY_OWNER,
            'password' => bcrypt('password'),
        ]);

        $company = Company::first() ?? Company::create([
            'owner_id' => $owner->id,
            'name' => 'NFT Payroll Demo Co',
        ]);

        $owner->update(['company_id' => $company->id]);

        OperationJob::factory()->count(20)->create(['company_id' => $company->id, 'user_id' => $owner->id]);
        ApprovalRequest::factory()->count(15)->create(['company_id' => $company->id, 'user_id' => $owner->id]);
        AuditLog::factory()->count(30)->create(['company_id' => $company->id, 'user_id' => $owner->id]);
        NotificationEvent::factory()->count(25)->create(['company_id' => $company->id, 'user_id' => $owner->id]);
        IntegrationConnection::factory()->count(10)->create(['company_id' => $company->id, 'user_id' => $owner->id]);
        TemplateDefinition::factory()->count(10)->create(['company_id' => $company->id, 'user_id' => $owner->id]);
        WalletValidation::factory()->count(20)->create(['company_id' => $company->id, 'user_id' => $owner->id]);
        BulkOperationRun::factory()->count(12)->create(['company_id' => $company->id, 'user_id' => $owner->id]);
        ExportJob::factory()->count(12)->create(['company_id' => $company->id, 'user_id' => $owner->id]);
        SystemHealthSnapshot::factory()->count(20)->create();
        RoleDefinition::factory()->count(6)->create(['company_id' => $company->id]);
    }
}
