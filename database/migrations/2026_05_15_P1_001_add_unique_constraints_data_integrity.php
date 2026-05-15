<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DELETE t1 FROM transactions t1 INNER JOIN transactions t2 WHERE t1.id > t2.id AND t1.chain_type = t2.chain_type AND t1.tx_hash = t2.tx_hash AND t1.tx_hash IS NOT NULL');

        Schema::table('transactions', function (Blueprint $table) {
            $table->unique(['chain_type', 'tx_hash'], 'transactions_chain_type_tx_hash_unique');
        });

        DB::statement("DELETE t1 FROM tokens t1 INNER JOIN tokens t2 WHERE t1.id > t2.id AND t1.chain_type = t2.chain_type AND t1.contract_address = t2.contract_address AND t1.contract_address IS NOT NULL AND t1.deleted_at IS NULL AND t2.deleted_at IS NULL");

        Schema::table('tokens', function (Blueprint $table) {
            $table->unique(['chain_type', 'contract_address'], 'tokens_chain_type_contract_address_unique');
        });

        DB::statement('DELETE wb1 FROM wallet_balances wb1 INNER JOIN wallet_balances wb2 WHERE wb1.id > wb2.id AND wb1.wallet_id = wb2.wallet_id AND wb1.token_id = wb2.token_id AND wb1.chain_type = wb2.chain_type');

        Schema::table('wallet_balances', function (Blueprint $table) {
            $table->unique(['wallet_id', 'token_id', 'chain_type'], 'wallet_balances_wallet_token_chain_unique');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique('transactions_chain_type_tx_hash_unique');
        });
        Schema::table('tokens', function (Blueprint $table) {
            $table->dropUnique('tokens_chain_type_contract_address_unique');
        });
        Schema::table('wallet_balances', function (Blueprint $table) {
            $table->dropUnique('wallet_balances_wallet_token_chain_unique');
        });
    }
};
