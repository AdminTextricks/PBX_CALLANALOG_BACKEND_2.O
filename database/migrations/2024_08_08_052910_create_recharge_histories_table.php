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
        Schema::create('recharge_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('user_id');
            $table->integer('invoice_id');
            $table->string('invoice_number');
            $table->decimal('current_balance', total: 10, places: 2);
            $table->decimal('added_balance', total: 10, places: 2);
            $table->decimal('total_balance', total: 10, places: 2);
            $table->string('currency');
            $table->enum('payment_type', ['Card', 'Crypto', 'Super Admin']);
            $table->string('recharged_by');
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recharge_historys');
    }
};
