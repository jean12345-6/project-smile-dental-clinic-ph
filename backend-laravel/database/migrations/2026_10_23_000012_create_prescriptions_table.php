<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id('prescription_id');
            $table->foreignId('treatment_id')->nullable()->constrained('treatments', 'treatment_id')->nullOnDelete();
            $table->foreignId('patient_id')->constrained('patients', 'patient_id')->cascadeOnDelete();
            $table->foreignId('dentist_id')->constrained('dentists', 'dentist_id')->cascadeOnDelete();
            $table->string('medicine');
            $table->string('dosage', 60)->nullable();
            $table->string('frequency', 60)->nullable();
            $table->string('duration', 60)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
