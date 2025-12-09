<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Kolon listesi üzerinden kontrol de yapabiliriz ama burada tek tek gidelim.
        
            if (!Schema::hasColumn('employees', 'employee_code')) {
                $table->string('employee_code')->nullable()->after('id');
            }
        
            if (!Schema::hasColumn('employees', 'tc_no')) {
                $table->string('tc_no')->nullable()->after('employee_code');
            }
        
            if (!Schema::hasColumn('employees', 'position')) {
                $table->string('position')->nullable()->after('name');
            }
        
            if (!Schema::hasColumn('employees', 'department')) {
                $table->string('department')->nullable()->after('position');
            }
        
            if (!Schema::hasColumn('employees', 'start_date')) {
                $table->date('start_date')->nullable()->after('department');
            }
        
            if (!Schema::hasColumn('employees', 'status')) {
                $table->string('status')->default('active')->after('start_date');
            }
        
            // Wallet alanı vs. eklemek istersen aynı patterni kullanabilirsin:
            // if (!Schema::hasColumn('employees', 'wallet_address')) { ... }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'employee_code',
                'tc_no',
                'position',
                'department',
                'start_date',
                'status',
            ]);
        });
    }
};
