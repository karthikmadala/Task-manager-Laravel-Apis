<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->softDeletes();
        });

        Schema::table('wallets', function (Blueprint $table): void {
            $table->softDeletes();
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->softDeletes();
        });

        Schema::table('tokens', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('tokens', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('wallets', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
