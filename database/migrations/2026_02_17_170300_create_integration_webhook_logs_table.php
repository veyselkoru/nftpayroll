<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('integration_webhook_logs')) {
            try {
                DB::statement('CREATE INDEX iwl_company_created_idx ON integration_webhook_logs (company_id, created_at)');
            } catch (\Throwable $e) {
            }
            try {
                DB::statement('CREATE INDEX iwl_conn_created_idx ON integration_webhook_logs (integration_connection_id, created_at)');
            } catch (\Throwable $e) {
            }
            return;
        }

        Schema::create('integration_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('integration_connection_id')->nullable()->constrained('integration_connections')->nullOnDelete();
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['success', 'failed'])->index();
            $table->string('endpoint')->nullable();
            $table->longText('payload')->nullable();
            $table->longText('response_body')->nullable();
            $table->unsignedInteger('http_status')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'created_at'], 'iwl_company_created_idx');
            $table->index(['integration_connection_id', 'created_at'], 'iwl_conn_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_webhook_logs');
    }
};
