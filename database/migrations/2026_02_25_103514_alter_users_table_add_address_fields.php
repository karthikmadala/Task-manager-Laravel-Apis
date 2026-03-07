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
        Schema::table('users', function (Blueprint $table) {
            $table->string('address')->nullable()->after('email');
            $table->string('phone')->nullable()->after('address');
            $table->string('city')->nullable()->after('phone');
            $table->string('zip')->nullable()->after('city');
            $table->boolean('consent_checkbox')->default(false)->after('zip');
            $table->string('role')->default('user')->index()->after('consent_checkbox');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['address', 'phone', 'city', 'zip', 'consent_checkbox', 'role']);
        });
    }
};
