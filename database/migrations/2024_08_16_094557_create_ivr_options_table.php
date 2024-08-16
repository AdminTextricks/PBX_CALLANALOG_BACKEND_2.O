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
        Schema::create('ivr_options', function (Blueprint $table) {
            $table->bigIncrements('id'); 
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('ivr_id');
            $table->integer('input_digit');
            $table->string('destination_type');
            $table->integer('destination_id');
            $table->integer('parent_id')->default(0);
            $table->timestamps();
            $table->foreign('ivr_id')->references('id')->on('ivrs');
			$table->foreign('company_id')->references('id')->on('companies'); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ivr_options');
    }
};
