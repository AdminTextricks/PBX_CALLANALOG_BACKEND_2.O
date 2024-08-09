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
        Schema::create('reseller_commission_of_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('reseller_id');
            $table->integer('invoice_id');
            // $table->smallInteger('country_id');
            $table->bigInteger('no_of_items');
            $table->decimal('total_amount', total: 8, places: 2);
            $table->decimal('commission_amount', total: 8, places: 2)->nullable()->default(0);
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('reseller_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reseller_commission_of_items');
    }
};
