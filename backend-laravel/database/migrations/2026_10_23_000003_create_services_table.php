<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id('service_id');
            $table->foreignId('clinic_id')->constrained('clinics', 'clinic_id')->cascadeOnDelete();
            $table->string('service_name');
            // how long one appointment of this service takes - the slot engine reads this
            $table->unsignedSmallInteger('duration_min')->default(30);
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
