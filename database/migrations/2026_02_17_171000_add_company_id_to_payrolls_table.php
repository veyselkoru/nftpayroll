<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            if (! Schema::hasColumn('payrolls', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
                $table->index(['company_id', 'created_at']);
            }
        });

        // Backfill existing records from employee relation when possible.
        if (Schema::hasColumn('payrolls', 'company_id')) {
            DB::table('payrolls')
                ->whereNull('company_id')
                ->orderBy('id')
                ->chunkById(500, function ($rows): void {
                    foreach ($rows as $row) {
                        $companyId = DB::table('employees')
                            ->where('id', $row->employee_id)
                            ->value('company_id');

                        if ($companyId) {
                            DB::table('payrolls')
                                ->where('id', $row->id)
                                ->update(['company_id' => $companyId]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            if (Schema::hasColumn('payrolls', 'company_id')) {
                $table->dropConstrainedForeignId('company_id');
            }
        });
    }
};

