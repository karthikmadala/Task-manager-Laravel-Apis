<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ico_used_nonces', function (Blueprint $table) {
            $table->id();
            $table->string('nonce')->index();
            $table->string('user_address', 255);
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at');
            $table->timestamps();
            $table->unique(['nonce', 'user_address'], 'ico_nonces_nonce_address_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ico_used_nonces');
    }
};
