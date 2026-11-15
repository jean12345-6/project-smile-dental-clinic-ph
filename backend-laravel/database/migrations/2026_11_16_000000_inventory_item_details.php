<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory')) {
            return;
        }
        Schema::table('inventory', function (Blueprint $t) {
            if (! Schema::hasColumn('inventory', 'item_code')) {
                $t->string('item_code', 40)->nullable();
            }
            if (! Schema::hasColumn('inventory', 'item_type')) {
                $t->string('item_type', 40)->nullable();
            }
            if (! Schema::hasColumn('inventory', 'unit_cost')) {
                $t->decimal('unit_cost', 10, 2)->default(0);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventory')) {
            return;
        }
        Schema::table('inventory', function (Blueprint $t) {
            foreach (['item_code', 'item_type', 'unit_cost'] as $c) {
                if (Schema::hasColumn('inventory', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
