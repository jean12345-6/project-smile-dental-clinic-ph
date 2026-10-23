<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id('feedback_id');
            $table->foreignId('appointment_id')->nullable()->constrained('appointments', 'appointment_id')->nullOnDelete();
            $table->foreignId('patient_id')->constrained('patients', 'patient_id')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('comment')->nullable();
            $table->date('date_submitted')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
