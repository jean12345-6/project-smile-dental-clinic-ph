<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatments', function (Blueprint $table) {
            $table->id('treatment_id');
            $table->foreignId('appointment_id')->nullable()->constrained('appointments', 'appointment_id')->nullOnDelete();
            $table->foreignId('patient_id')->constrained('patients', 'patient_id')->cascadeOnDelete();
            $table->foreignId('dentist_id')->constrained('dentists', 'dentist_id')->cascadeOnDelete();
            $table->string('tooth_no', 10)->nullable();
            $table->string('procedure_name');
            $table->text('notes')->nullable();
            $table->date('treatment_date');
            $table->decimal('cost', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatments');
    }
};
