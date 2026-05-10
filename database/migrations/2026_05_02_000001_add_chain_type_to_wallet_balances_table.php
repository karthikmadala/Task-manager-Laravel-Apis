<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_balances', function (Blueprint $table): void {
            $table->string('chain_type', 32)->nullable()->after('token_id');
        });

        DB::table('wallets')
            ->select(['id', 'chain_type'])
            ->orderBy('id')
            ->each(function (object $wallet): void {
                DB::table('wallet_balances')
                    ->where('wallet_id', $wallet->id)
                    ->whereNull('chain_type')
                    ->update(['chain_type' => $wallet->chain_type]);
            });

        Schema::table('wallet_balances', function (Blueprint $table): void {
            $table->string('chain_type', 32)->nullable(false)->change();
            $table->dropUnique(['wallet_id', 'token_id']);
            $table->unique(['wallet_id', 'chain_type', 'token_id'], 'wallet_balances_wallet_chain_token_unique');
            $table->index(['wallet_id', 'chain_type'], 'wallet_balances_wallet_chain_index');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_balances', function (Blueprint $table): void {
            $table->dropUnique('wallet_balances_wallet_chain_token_unique');
            $table->dropIndex('wallet_balances_wallet_chain_index');
            $table->unique(['wallet_id', 'token_id']);
            $table->dropColumn('chain_type');
        });
    }
};
