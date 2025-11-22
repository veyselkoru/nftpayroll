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
        Schema::create('nft_mints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained();
            $table->string('wallet_address'); // hedef cüzdan
            $table->string('ipfs_cid');       // mint edilen metadata (ipfs)
            $table->string('tx_hash')->nullable();
            $table->string('status')->default('pending'); 
            // pending | sending | sent | failed
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nft_mints');
    }
};
