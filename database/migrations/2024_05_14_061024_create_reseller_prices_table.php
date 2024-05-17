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
        Schema::create('reseller_prices', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->integer('user_id')->nullable();
			$table->integer('country_id')->default(0);
            $table->enum('commission_type', ['Fixed Amount', 'Percentage'])->nullable();
            $table->enum('product',['TFN', 'Extension'])->nullable();    
            $table->decimal('price', 10, 2)->default(0);
            $table->tinyInteger('status')->default('0');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reseller_prices');
    }
};
