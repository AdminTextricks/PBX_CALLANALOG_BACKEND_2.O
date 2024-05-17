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
        Schema::create('outbound_call_rates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tariff_id')->nullable(); 
            $table->unsignedBigInteger('trunk_id')->nullable(); 
			$table->string('country_prefix', 250)->nullable();  
            $table->string('selling_rate',50)->nullable()->default(0);
            $table->string('init_block',50)->nullable()->default(0);
            $table->string('billing_block',30)->nullable()->default(0);
            $table->integer('start_date')->nullable();
            $table->string('stop_date',50)->nullable();
			$table->tinyInteger('status')->default(0);
            $table->timestamps();
            $table->foreign('tariff_id')->references('id')->on('tariffs');
            $table->foreign('trunk_id')->references('id')->on('trunks');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outbound_call_rates');
    }
};
