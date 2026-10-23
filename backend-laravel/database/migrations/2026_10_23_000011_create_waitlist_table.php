<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist', function (Blueprint $table) {
            $table->id('waitlist_id');
            $table->foreignId('patient_id')->constrained('patients', 'patient_id')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services', 'service_id')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches', 'branch_id')->cascadeOnDelete();
            $table->foreignId('dentist_id')->nullable()->constrained('dentists', 'dentist_id')->nullOnDelete();
            $table->date('preferred_date')->nullable();
            // waiting | offered | accepted | expired | cancelled
            $table->string('status', 20)->default('waiting');
            $table->dateTime('offered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist');
    }
};
