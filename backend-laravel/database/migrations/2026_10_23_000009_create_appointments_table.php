<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id('appointment_id');
            $table->foreignId('patient_id')->constrained('patients', 'patient_id')->cascadeOnDelete();
            $table->foreignId('dentist_id')->constrained('dentists', 'dentist_id')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches', 'branch_id')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services', 'service_id')->cascadeOnDelete();
            $table->dateTime('start_time');
            // start_time plus the service duration - kept so overlap checks are one query
            $table->dateTime('end_time');
            // pending | confirmed | checked_in | completed | cancelled | no_show
            $table->string('status', 20)->default('pending');
            $table->boolean('risk_flag')->default(false);
            // chatbot | portal | walk_in
            $table->string('booked_via', 20)->default('portal');
            $table->dateTime('check_in_time')->nullable();
            // set when this visit was booked as the follow-up of an earlier one
            $table->unsignedBigInteger('follow_up_of')->nullable();
            $table->timestamps();

            $table->index(['dentist_id', 'start_time']);
            $table->index(['branch_id', 'start_time']);
            $table->index(['patient_id', 'start_time']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreign('follow_up_of')
                ->references('appointment_id')->on('appointments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
