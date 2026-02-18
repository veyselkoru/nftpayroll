<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('approval_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('approval_requests', 'employee_id')) {
                $table->foreignId('employee_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            }
            if (!Schema::hasColumn('approval_requests', 'payroll_id')) {
                $table->foreignId('payroll_id')->nullable()->after('employee_id')->constrained()->nullOnDelete();
            }
            if (!Schema::hasColumn('approval_requests', 'policy_key')) {
                $table->string('policy_key')->nullable()->after('type');
            }
            $table->index(['payroll_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('approval_requests', function (Blueprint $table) {
            foreach (['employee_id','payroll_id'] as $fk) {
                if (Schema::hasColumn('approval_requests', $fk)) {
                    $table->dropConstrainedForeignId($fk);
                }
            }
            foreach (['policy_key'] as $col) {
                if (Schema::hasColumn('approval_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
