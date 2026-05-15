<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tokens', function (Blueprint $table) {
            $table->decimal('current_price_usd', 36, 18)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tokens', function (Blueprint $table) {
            $table->float('current_price_usd')->nullable()->change();
        });
    }
};
