<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('nft_mints', function (Blueprint $table) {
            if (!Schema::hasColumn('nft_mints', 'network')) {
                $table->string('network')->nullable()->after('token_id');
            }
            if (!Schema::hasColumn('nft_mints', 'gas_used')) {
                $table->unsignedBigInteger('gas_used')->nullable()->after('network');
            }
            if (!Schema::hasColumn('nft_mints', 'gas_fee_eth')) {
                $table->decimal('gas_fee_eth', 20, 10)->nullable()->after('gas_used');
            }
            if (!Schema::hasColumn('nft_mints', 'gas_fee_fiat')) {
                $table->decimal('gas_fee_fiat', 20, 4)->nullable()->after('gas_fee_eth');
            }
            if (!Schema::hasColumn('nft_mints', 'cost_source')) {
                $table->string('cost_source')->nullable()->after('gas_fee_fiat');
            }
            if (!Schema::hasColumn('nft_mints', 'duration_ms')) {
                $table->unsignedInteger('duration_ms')->nullable()->after('cost_source');
            }
        });

        Schema::table('payrolls', function (Blueprint $table) {
            if (!Schema::hasColumn('payrolls', 'template_id')) {
                $afterColumn = Schema::hasColumn('payrolls', 'company_id') ? 'company_id' : 'id';
                $table->foreignId('template_id')->nullable()->after($afterColumn)->constrained('template_definitions')->nullOnDelete();
            }
            if (!Schema::hasColumn('payrolls', 'template_version')) {
                $table->unsignedInteger('template_version')->nullable()->after('template_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('nft_mints', function (Blueprint $table) {
            foreach (['network','gas_used','gas_fee_eth','gas_fee_fiat','cost_source','duration_ms'] as $col) {
                if (Schema::hasColumn('nft_mints', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('payrolls', function (Blueprint $table) {
            if (Schema::hasColumn('payrolls', 'template_id')) {
                $table->dropConstrainedForeignId('template_id');
            }
            if (Schema::hasColumn('payrolls', 'template_version')) {
                $table->dropColumn('template_version');
            }
        });
    }
};
