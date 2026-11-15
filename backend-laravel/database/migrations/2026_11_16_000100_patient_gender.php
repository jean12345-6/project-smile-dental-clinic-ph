<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('patients') && ! Schema::hasColumn('patients', 'gender')) {
            Schema::table('patients', fn (Blueprint $t) => $t->string('gender', 20)->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('patients') && Schema::hasColumn('patients', 'gender')) {
            Schema::table('patients', fn (Blueprint $t) => $t->dropColumn('gender'));
        }
    }
};
