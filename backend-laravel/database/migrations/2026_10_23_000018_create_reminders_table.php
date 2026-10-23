<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id('reminder_id');
            $table->foreignId('appointment_id')->constrained('appointments', 'appointment_id')->cascadeOnDelete();
            // reminder | confirmation | slot_offer | follow_up
            $table->string('type', 30)->default('reminder');
            // sms | email
            $table->string('channel', 20)->default('sms');
            $table->dateTime('send_time')->nullable();
            // pending | sent | failed
            $table->string('send_status', 20)->default('pending');
            $table->timestamps();

            $table->index(['appointment_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
