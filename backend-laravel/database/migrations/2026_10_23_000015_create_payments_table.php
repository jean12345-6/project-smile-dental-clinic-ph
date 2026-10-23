<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id('payment_id');
            $table->foreignId('invoice_id')->constrained('invoices', 'invoice_id')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            // cash | card | gcash | bank_transfer
            $table->string('method', 30)->default('cash');
            $table->dateTime('paid_at');
            // the staff account that accepted the payment
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
