<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wallet_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('token_id')->constrained()->cascadeOnDelete();

            $table->decimal('balance', 36, 18)->default(0);
            $table->decimal('balance_usd', 24, 8)->nullable();

            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->unique(['wallet_id', 'token_id']);
            $table->index('wallet_id');
            $table->index('fetched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_balances');
    }
};