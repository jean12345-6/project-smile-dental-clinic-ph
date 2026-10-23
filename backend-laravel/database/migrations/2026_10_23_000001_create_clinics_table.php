<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinics', function (Blueprint $table) {
            $table->id('clinic_id');
            $table->string('clinic_name');
            $table->string('logo_path')->nullable();
            $table->string('contact_no', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinics');
    }
};
