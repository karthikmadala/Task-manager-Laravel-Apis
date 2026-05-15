<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
            $table->string('entity_type', 100)->index();
            $table->string('entity_id', 255)->nullable()->index();
            $table->string('action', 100)->index();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};
