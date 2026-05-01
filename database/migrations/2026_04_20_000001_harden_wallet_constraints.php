<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table): void {
            $table->string('metamask_nonce', 255)->nullable()->change();
            $table->dropUnique('wallets_user_id_address_unique');
            $table->unique(['user_id', 'chain_type', 'address'], 'wallets_user_id_chain_type_address_unique');
            $table->dropIndex(['user_id', 'chain_type']);
            $table->index(['user_id', 'chain_type', 'is_active'], 'wallets_user_id_chain_type_is_active_index');
            $table->dropIndex(['address']);
            $table->index(['address', 'chain_type'], 'wallets_address_chain_type_index');
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table): void {
            $table->dropUnique('wallets_user_id_chain_type_address_unique');
            $table->unique(['user_id', 'address'], 'wallets_user_id_address_unique');
            $table->dropIndex('wallets_user_id_chain_type_is_active_index');
            $table->index(['user_id', 'chain_type']);
            $table->dropIndex('wallets_address_chain_type_index');
            $table->index('address');
            $table->string('metamask_nonce', 64)->nullable()->change();
        });
    }
};
