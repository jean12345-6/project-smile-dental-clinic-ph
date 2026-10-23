<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id('branch_id');
            $table->foreignId('clinic_id')->constrained('clinics', 'clinic_id')->cascadeOnDelete();
            $table->string('branch_name');
            $table->string('address')->nullable();
            $table->string('contact_no', 30)->nullable();
            $table->time('open_time')->nullable();
            $table->time('close_time')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
