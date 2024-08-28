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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            // $table->bigInteger('company_id')->nullable()->default(0);
            $table->integer('country_id')->nullable();
            $table->integer('invoice_id')->nullable();
            $table->string('item_type')->nullable();
            $table->bigInteger('item_number')->nullable();
            $table->decimal('item_price', total: 8, places: 2)->nullable()->default(0);
            $table->enum('item_category', ['Purchase', 'Renew'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
