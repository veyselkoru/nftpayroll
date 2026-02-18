<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wallet_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('wallet_address', 42)->index();
            $table->string('network')->default('sepolia')->index();
            $table->enum('status', ['valid', 'invalid'])->index();
            $table->string('message')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_validations');
    }
};
