<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dentists', function (Blueprint $table) {
            $table->id('dentist_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('full_name');
            $table->string('specialization')->nullable();
            $table->string('license_no', 60)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dentists');
    }
};
