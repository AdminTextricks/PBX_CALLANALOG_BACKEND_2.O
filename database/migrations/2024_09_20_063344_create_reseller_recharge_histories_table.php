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
        Schema::create('reseller_recharge_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('old_balance', total: 10, places: 2);
            $table->decimal('added_balance', total: 10, places: 2)->nullable();
            $table->decimal('total_balance', total: 10, places: 2)->nullable();
            $table->string('currency'); 
            $table->enum('payment_type', ['Card', 'Crypto', 'Comission Amount'])->nullable();
            $table->string('transaction_id');
            $table->string('stripe_charge_id')->nullable();        
            $table->string('recharged_by');
            $table->tinyInteger( 'status');
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reseller_recharge_histories');
    }
};
