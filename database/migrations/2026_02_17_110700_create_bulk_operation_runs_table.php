<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bulk_operation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type')->index();
            $table->enum('status', ['queued', 'running', 'failed', 'completed'])->default('queued')->index();
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('processed_items')->default(0);
            $table->unsignedInteger('failed_items')->default(0);
            $table->json('payload')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_operation_runs');
    }
};
