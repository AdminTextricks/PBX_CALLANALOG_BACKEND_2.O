<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('company_id')->nullable()->default(0);
            $table->bigInteger('invoice_id');
            $table->string('ip_address')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('order_id')->nullable();
            $table->string('item_numbers')->nullable();
            $table->string('payment_type')->nullable();
            $table->string('payment_currency')->nullable();
            $table->string('payment_price')->nullable();
            $table->string('transaction_id');
            $table->string('stripe_charge_id');
            $table->tinyInteger('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
