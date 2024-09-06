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
        Schema::create('ivrs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->integer('country_id')->default(0);;
            //$table->tinyInteger('input_auth_type')->nullable();
            $table->string('name', 256)->nullable()->index();
            $table->string('description', 256)->nullable();            
            $table->integer('ivr_media_id')->default(0);
            $table->string('timeout',256)->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies');
            //$table->foreign('ivr_media_id')->references('id')->on('ivr_media');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ivrs');
    }
};
