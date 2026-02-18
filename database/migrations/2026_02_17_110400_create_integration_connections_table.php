<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('integration_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('provider')->index();
            $table->enum('status', ['active', 'inactive', 'error'])->default('inactive')->index();
            $table->json('config')->nullable();
            $table->timestamp('last_test_at')->nullable();
            $table->enum('last_test_status', ['passed', 'failed'])->nullable()->index();
            $table->timestamps();
            $table->index(['company_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_connections');
    }
};
