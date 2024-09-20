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
        Schema::create('ivr_direct_destinations', function (Blueprint $table) {
            $table->bigIncrements('id'); 
            $table->unsignedBigInteger('ivr_id');
            $table->enum('authentication',['0','1'])->default(0);
            $table->integer('authentication_type')->nullable();
            $table->integer('authentication_digit')->nullable();
            $table->unsignedBigInteger('destination_type_id');
            $table->integer('destination_id');
            $table->timestamps();
            $table->foreign('ivr_id')->references('id')->on('ivrs');
			$table->foreign('destination_type_id')->references('id')->on('destination_types'); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ivr_direct_destinations');
    }
};
