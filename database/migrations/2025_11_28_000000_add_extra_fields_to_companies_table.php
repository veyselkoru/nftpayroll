<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'type')) {
                $table->string('type')->nullable()->after('name');
            }
        
            if (!Schema::hasColumn('companies', 'registration_number')) {
                $table->string('registration_number')->nullable()->after('tax_number');
            }
        
            if (!Schema::hasColumn('companies', 'address')) {
                $table->string('address')->nullable()->after('city');
            }
        
            if (!Schema::hasColumn('companies', 'contact_phone')) {
                $table->string('contact_phone')->nullable()->after('address');
            }
        
            if (!Schema::hasColumn('companies', 'contact_email')) {
                $table->string('contact_email')->nullable()->after('contact_phone');
            }
        });
        
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'type',
                'registration_number',
                'address',
                'contact_phone',
                'contact_email',
            ]);
        });
    }
};
