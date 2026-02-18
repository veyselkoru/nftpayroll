<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('nft_mints', function (Blueprint $table) {
            if (! Schema::hasColumn('nft_mints', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('payroll_id')->constrained()->nullOnDelete();
            }
        });

        DB::table('nft_mints')
            ->whereNull('company_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $companyId = DB::table('payrolls')
                        ->where('id', $row->payroll_id)
                        ->value('company_id');

                    if ($companyId) {
                        DB::table('nft_mints')->where('id', $row->id)->update(['company_id' => $companyId]);
                    }
                }
            });

        $this->createIndexIfNotExists('operation_jobs', 'operation_jobs_status_idx', ['status']);
        $this->createIndexIfNotExists('operation_jobs', 'operation_jobs_company_id_idx', ['company_id']);
        $this->createIndexIfNotExists('operation_jobs', 'operation_jobs_payroll_id_idx', ['payroll_id']);
        $this->createIndexIfNotExists('operation_jobs', 'operation_jobs_updated_at_idx', ['updated_at']);

        $this->createIndexIfNotExists('nft_mints', 'nft_mints_status_idx', ['status']);
        $this->createIndexIfNotExists('nft_mints', 'nft_mints_company_id_idx', ['company_id']);
        $this->createIndexIfNotExists('nft_mints', 'nft_mints_payroll_id_idx', ['payroll_id']);
        $this->createIndexIfNotExists('nft_mints', 'nft_mints_updated_at_idx', ['updated_at']);
    }

    public function down(): void
    {
        foreach ([
            ['operation_jobs', 'operation_jobs_status_idx'],
            ['operation_jobs', 'operation_jobs_company_id_idx'],
            ['operation_jobs', 'operation_jobs_payroll_id_idx'],
            ['operation_jobs', 'operation_jobs_updated_at_idx'],
            ['nft_mints', 'nft_mints_status_idx'],
            ['nft_mints', 'nft_mints_company_id_idx'],
            ['nft_mints', 'nft_mints_payroll_id_idx'],
            ['nft_mints', 'nft_mints_updated_at_idx'],
        ] as [$table, $index]) {
            try {
                DB::statement("DROP INDEX {$index} ON {$table}");
            } catch (\Throwable $e) {
            }
        }

        Schema::table('nft_mints', function (Blueprint $table) {
            if (Schema::hasColumn('nft_mints', 'company_id')) {
                $table->dropConstrainedForeignId('company_id');
            }
        });
    }

    private function createIndexIfNotExists(string $table, string $indexName, array $columns): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $exists = DB::table('information_schema.statistics')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', $table)
                ->where('index_name', $indexName)
                ->exists();
            if (! $exists) {
                DB::statement(sprintf(
                    'CREATE INDEX %s ON %s (%s)',
                    $indexName,
                    $table,
                    implode(', ', $columns)
                ));
            }
            return;
        }

        if ($driver === 'sqlite') {
            $exists = DB::table('sqlite_master')
                ->where('type', 'index')
                ->where('name', $indexName)
                ->exists();
            if (! $exists) {
                DB::statement(sprintf(
                    'CREATE INDEX %s ON %s (%s)',
                    $indexName,
                    $table,
                    implode(', ', $columns)
                ));
            }
        }
    }
};

