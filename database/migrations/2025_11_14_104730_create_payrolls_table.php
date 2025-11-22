<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('gross_salary', 15, 2)->nullable();
            $table->decimal('net_salary', 15, 2)->nullable();
    
            // KVKK sebebiyle detayları encrypted JSON olarak tutacağız (ileride)
            $table->longText('encrypted_payload')->nullable(); // şifreli json
            $table->string('ipfs_cid')->nullable();            // IPFS CID
            $table->string('status')->default('pending');      // pending|queued|minted|failed
    
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
