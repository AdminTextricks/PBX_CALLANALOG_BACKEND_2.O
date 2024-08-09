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
        Schema::create('carts', function (Blueprint $table) {
            $table->bigIncrements('id');
            // $table->foreignId('user_id')->constrained()->onDelete('cascade')->onUpdate('cascade');
            $table->bigInteger('company_id')->index()->nullable()->default(0);
            $table->integer('country_id');
            $table->integer('item_id');
            $table->bigInteger('item_number');
            $table->enum('item_type', ['TFN', 'Extension']);
            $table->decimal('item_price', total: 8, places: 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
