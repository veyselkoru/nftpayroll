<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('system_health_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('service')->index();
            $table->enum('status', ['healthy', 'warning', 'down'])->index();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->decimal('error_rate', 5, 2)->default(0);
            $table->decimal('uptime_percent', 5, 2)->default(100);
            $table->unsignedInteger('incident_count')->default(0);
            $table->timestamp('captured_at')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_health_snapshots');
    }
};
