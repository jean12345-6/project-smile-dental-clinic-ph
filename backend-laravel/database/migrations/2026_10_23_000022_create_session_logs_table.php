<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_logs', function (Blueprint $table) {
            $table->id('session_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('login_time');
            $table->dateTime('logout_time')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('device')->nullable();
            // logout | timeout | revoked
            $table->string('end_reason', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_logs');
    }
};
