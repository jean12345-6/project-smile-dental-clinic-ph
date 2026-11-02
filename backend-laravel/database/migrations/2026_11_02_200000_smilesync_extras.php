<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clinic-level settings (reminder timing, no-show threshold ...) — FR-09
        if (! Schema::hasTable('clinic_settings')) {
            Schema::create('clinic_settings', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('clinic_id')->nullable();
                $t->string('key', 80);
                $t->string('value', 255)->nullable();
                $t->timestamps();
                $t->unique(['clinic_id', 'key']);
            });
        }

        // Patient records are archived, never erased — FR-29
        if (Schema::hasTable('patients') && ! Schema::hasColumn('patients', 'status')) {
            Schema::table('patients', fn (Blueprint $t) => $t->string('status', 20)->default('active'));
        }

        // A waitlist offer remembers the exact slot that opened — FR-20
        if (Schema::hasTable('waitlist')) {
            Schema::table('waitlist', function (Blueprint $t) {
                foreach (['offered_start' => 'dateTime', 'offered_end' => 'dateTime'] as $c => $type) {
                    if (! Schema::hasColumn('waitlist', $c)) {
                        $t->dateTime($c)->nullable();
                    }
                }
                foreach (['offered_dentist_id', 'offered_branch_id'] as $c) {
                    if (! Schema::hasColumn('waitlist', $c)) {
                        $t->unsignedBigInteger($c)->nullable();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_settings');
    }
};
