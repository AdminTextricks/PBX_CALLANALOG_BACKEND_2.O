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
        Schema::create('countries', function (Blueprint $table) {
            //$table->id();
			$table->bigIncrements('id');
            $table->string('country_name');
			$table->string('iso3');
			$table->string('iso2');
			$table->string('phone_code');
			$table->string('currency');
			$table->string('currency_symbol')->charset('utf8mb4')->collate('utf8mb4_unicode_ci');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
