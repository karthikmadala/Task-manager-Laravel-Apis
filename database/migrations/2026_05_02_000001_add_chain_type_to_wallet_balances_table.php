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

        DB::statement('
            UPDATE wallet_balances wb
            INNER JOIN wallets w ON w.id = wb.wallet_id
            SET wb.chain_type = w.chain_type
            WHERE wb.chain_type IS NULL
        ');

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
