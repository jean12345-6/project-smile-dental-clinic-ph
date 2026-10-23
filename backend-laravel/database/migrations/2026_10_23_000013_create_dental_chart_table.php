<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dental_chart', function (Blueprint $table) {
            $table->id('chart_id');
            $table->foreignId('patient_id')->constrained('patients', 'patient_id')->cascadeOnDelete();
            $table->string('tooth_no', 10);
            $table->string('condition', 100)->nullable();
            $table->date('date_updated')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'tooth_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dental_chart');
    }
};
