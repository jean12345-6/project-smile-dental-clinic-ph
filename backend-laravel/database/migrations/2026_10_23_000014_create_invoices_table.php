<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id('invoice_id');
            $table->foreignId('appointment_id')->nullable()->constrained('appointments', 'appointment_id')->nullOnDelete();
            $table->foreignId('patient_id')->constrained('patients', 'patient_id')->cascadeOnDelete();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('balance', 10, 2)->default(0);
            $table->date('date_issued');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
