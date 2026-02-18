<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('export_jobs', function (Blueprint $table) {
            if (!Schema::hasColumn('export_jobs', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('downloaded_at');
            }
        });

        Schema::table('bulk_operation_runs', function (Blueprint $table) {
            if (!Schema::hasColumn('bulk_operation_runs', 'results')) {
                $table->json('results')->nullable()->after('payload');
            }
        });
    }

    public function down(): void
    {
        Schema::table('export_jobs', function (Blueprint $table) {
            if (Schema::hasColumn('export_jobs', 'completed_at')) {
                $table->dropColumn('completed_at');
            }
        });

        Schema::table('bulk_operation_runs', function (Blueprint $table) {
            if (Schema::hasColumn('bulk_operation_runs', 'results')) {
                $table->dropColumn('results');
            }
        });
    }
};
