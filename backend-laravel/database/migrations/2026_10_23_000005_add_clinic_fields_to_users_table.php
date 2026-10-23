<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // SuperAdmin belongs to no clinic, so both keys are nullable
            $table->foreignId('clinic_id')->nullable()->after('id')
                ->constrained('clinics', 'clinic_id')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->after('clinic_id')
                ->constrained('branches', 'branch_id')->nullOnDelete();
            // superadmin | clinic_admin | receptionist | dentist | patient
            $table->string('role', 30)->default('patient')->after('password');
            $table->string('status', 20)->default('active')->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['clinic_id']);
            $table->dropForeign(['branch_id']);
            $table->dropColumn(['clinic_id', 'branch_id', 'role', 'status']);
        });
    }
};
