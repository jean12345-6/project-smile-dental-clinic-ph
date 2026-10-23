<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_logs', function (Blueprint $table) {
            $table->id('chat_id');
            // null for a visitor who has not logged in yet
            $table->foreignId('patient_id')->nullable()->constrained('patients', 'patient_id')->nullOnDelete();
            $table->text('message_in')->nullable();
            $table->text('message_out')->nullable();
            // book | reschedule | cancel | faq | personal | escalate
            $table->string('intent', 40)->nullable();
            $table->boolean('escalated')->default(false);
            $table->dateTime('timestamp')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_logs');
    }
};
