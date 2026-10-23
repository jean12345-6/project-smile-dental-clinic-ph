<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ocr_batches', function (Blueprint $table) {
            $table->id('batch_id');
            $table->foreignId('patient_id')->nullable()->constrained('patients', 'patient_id')->nullOnDelete();
            $table->string('image_path');
            $table->longText('extracted_text')->nullable();
            // the staff account that approved the extracted text
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            // pending | verified | rejected
            $table->string('verify_status', 20)->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocr_batches');
    }
};
