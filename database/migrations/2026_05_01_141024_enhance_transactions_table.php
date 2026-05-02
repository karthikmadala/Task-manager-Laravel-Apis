<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Signing method
            $table->string('signing_method', 20)->default('client')->after('status');

            // Gas parameters (EIP-1559 support)
            $table->decimal('gas_limit', 20, 8)->nullable()->after('gas_price_gwei');
            $table->decimal('max_fee_per_gas', 20, 8)->nullable()->after('gas_limit');
            $table->decimal('max_priority_fee_per_gas', 20, 8)->nullable()->after('max_fee_per_gas');

            // Monitoring
            $table->integer('confirmations_count')->default(0)->after('block_number');
            $table->integer('retry_count')->default(0)->after('confirmations_count');
            $table->integer('broadcast_attempts')->default(0)->after('retry_count');

            // Contract interaction support
            $table->string('contract_address', 255)->nullable()->after('to_address');
            $table->string('method_signature', 255)->nullable()->after('contract_address');
            $table->json('method_params')->nullable()->after('method_signature');

            // Indexes for performance
            $table->index(['status', 'created_at']);
            $table->index(['chain_type', 'from_address']);
            $table->index(['chain_type', 'to_address']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['chain_type', 'from_address']);
            $table->dropIndex(['chain_type', 'to_address']);

            // Drop columns
            $table->dropColumn([
                'signing_method',
                'gas_limit',
                'max_fee_per_gas',
                'max_priority_fee_per_gas',
                'confirmations_count',
                'retry_count',
                'broadcast_attempts',
                'contract_address',
                'method_signature',
                'method_params',
            ]);
        });
    }
};
