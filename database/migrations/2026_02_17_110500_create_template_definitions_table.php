<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('template_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type')->index();
            $table->unsignedInteger('version')->default(1);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft')->index();
            $table->longText('body')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_definitions');
    }
};
