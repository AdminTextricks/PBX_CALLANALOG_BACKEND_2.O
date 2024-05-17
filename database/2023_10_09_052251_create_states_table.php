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
        Schema::create('states', function (Blueprint $table) {
            //$table->id();
			$table->bigIncrements('id');
			$table->bigInteger('country_id')->unsigned()->index()->nullable();
			$table->foreign('country_id')->references('id')->on('countries');
			$table->string('iso3');
            $table->string('state_name');
			$table->string('state_code');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('states');
    }
};
