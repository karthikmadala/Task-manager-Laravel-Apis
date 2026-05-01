<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // FIX: use foreignId (BIGINT)
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('chain_type', 20);
            $table->string('wallet_type', 20);
            $table->string('address', 255);
            $table->string('label', 100)->nullable();
            $table->string('metamask_nonce', 64)->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['user_id', 'address']);
            $table->index(['user_id', 'chain_type']);
            $table->index('address');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};