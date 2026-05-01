<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // FIX: BIGINT foreign key
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('method', 10);
            $table->string('path', 500);
            $table->smallInteger('status_code')->unsigned()->nullable();
            $table->string('ip', 45)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['path', 'status_code']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_logs');
    }
};