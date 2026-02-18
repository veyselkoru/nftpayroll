<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('operation_jobs', function (Blueprint $table) {
            if (!Schema::hasColumn('operation_jobs', 'employee_id')) {
                $table->foreignId('employee_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            }
            if (!Schema::hasColumn('operation_jobs', 'payroll_id')) {
                $table->foreignId('payroll_id')->nullable()->after('employee_id')->constrained()->nullOnDelete();
            }
            if (!Schema::hasColumn('operation_jobs', 'nft_mint_id')) {
                $table->foreignId('nft_mint_id')->nullable()->after('payroll_id')->constrained('nft_mints')->nullOnDelete();
            }
            if (!Schema::hasColumn('operation_jobs', 'triggered_by_user_id')) {
                $table->foreignId('triggered_by_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('operation_jobs', 'retry_count')) {
                $table->unsignedInteger('retry_count')->default(0)->after('max_attempts');
            }
            if (!Schema::hasColumn('operation_jobs', 'tx_hash')) {
                $table->string('tx_hash')->nullable()->after('error_message');
            }
            if (!Schema::hasColumn('operation_jobs', 'token_id')) {
                $table->unsignedBigInteger('token_id')->nullable()->after('tx_hash');
            }
            if (!Schema::hasColumn('operation_jobs', 'duration_ms')) {
                $table->unsignedInteger('duration_ms')->nullable()->after('token_id');
            }
            $table->index(['payroll_id', 'status']);
            $table->index(['nft_mint_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('operation_jobs', function (Blueprint $table) {
            foreach (['employee_id','payroll_id','nft_mint_id','triggered_by_user_id'] as $fk) {
                if (Schema::hasColumn('operation_jobs', $fk)) {
                    $table->dropConstrainedForeignId($fk);
                }
            }
            foreach (['retry_count','tx_hash','token_id','duration_ms'] as $col) {
                if (Schema::hasColumn('operation_jobs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
