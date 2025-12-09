<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            // Temel dönem/ödeme bilgisi
            $table->date('payment_date')->nullable()->after('period_end');
            $table->string('currency', 10)->nullable()->after('payment_date');

            // Ücret detayları (gross_salary, net_salary zaten var)
            $table->decimal('bonus', 15, 2)->nullable()->after('net_salary');
            $table->decimal('deductions_total', 15, 2)->nullable()->after('bonus');

            // İşveren imza bilgisi
            $table->string('employer_sign_name')->nullable()->after('deductions_total');
            $table->string('employer_sign_title')->nullable()->after('employer_sign_name');

            // Batch / harici sistem referansları
            $table->string('batch_id')->nullable()->after('employer_sign_title');
            $table->string('external_batch_ref')->nullable()->after('batch_id');
            $table->string('external_ref')->nullable()->after('external_batch_ref');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn([
                'payment_date',
                'currency',
                'bonus',
                'deductions_total',
                'employer_sign_name',
                'employer_sign_title',
                'batch_id',
                'external_batch_ref',
                'external_ref',
            ]);
        });
    }
};
