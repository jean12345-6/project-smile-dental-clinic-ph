<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id('patient_id');
            // walk-in patients may have no login account yet
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('clinic_id')->constrained('clinics', 'clinic_id')->cascadeOnDelete();
            $table->string('full_name');
            $table->date('birthdate')->nullable();
            $table->string('address')->nullable();
            $table->string('contact_no', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('medical_history')->nullable();
            // raised by the no-show job, read by the risk flag on new bookings
            $table->unsignedInteger('no_show_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
