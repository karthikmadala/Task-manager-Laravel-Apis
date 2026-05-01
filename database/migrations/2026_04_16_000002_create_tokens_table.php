<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('symbol', 20);
            $table->string('name', 100);
            $table->string('chain_type', 20);
            $table->string('coingecko_id', 100)->nullable();
            $table->string('contract_address', 255)->nullable();
            $table->tinyInteger('decimals')->unsigned()->default(18);

            $table->decimal('current_price_usd', 24, 8)->nullable();
            $table->timestamp('price_updated_at')->nullable();

            $table->timestamps();

            $table->unique(['symbol', 'chain_type']);
            $table->index(['chain_type', 'symbol']);
            $table->index('coingecko_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tokens');
    }
};