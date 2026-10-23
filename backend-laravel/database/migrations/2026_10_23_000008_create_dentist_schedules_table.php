<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dentist_schedules', function (Blueprint $table) {
            $table->id('schedule_id');
            $table->foreignId('dentist_id')->constrained('dentists', 'dentist_id')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches', 'branch_id')->cascadeOnDelete();
            // 0 = Sunday ... 6 = Saturday. Null on a row that only records a leave date.
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            $table->date('leave_date')->nullable();
            $table->timestamps();

            $table->index(['dentist_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dentist_schedules');
    }
};
