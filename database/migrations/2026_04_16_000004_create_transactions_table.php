<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('wallet_id')->constrained()->cascadeOnDelete();

            // FIX: BIGINT
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->foreignUuid('token_id')->nullable()->constrained()->nullOnDelete();

            $table->string('tx_hash', 255)->nullable();
            $table->string('from_address', 255);
            $table->string('to_address', 255);

            $table->decimal('amount', 36, 18);
            $table->string('chain_type', 20);
            $table->string('status', 20)->default('pending');

            $table->decimal('gas_used', 20, 8)->nullable();
            $table->decimal('gas_price_gwei', 20, 8)->nullable();
            $table->decimal('fee_usd', 16, 8)->nullable();

            $table->unsignedBigInteger('block_number')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();

            $table->timestamps();

            $table->index('tx_hash');
            $table->index(['wallet_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['chain_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};