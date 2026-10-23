<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory', function (Blueprint $table) {
            $table->id('item_id');
            $table->foreignId('branch_id')->constrained('branches', 'branch_id')->cascadeOnDelete();
            $table->string('item_name');
            $table->integer('quantity')->default(0);
            $table->string('unit', 30)->nullable();
            // the alert fires when quantity reaches this number
            $table->unsignedInteger('reorder_level')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory');
    }
};
