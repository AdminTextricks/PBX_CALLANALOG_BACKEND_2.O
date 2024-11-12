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
        Schema::create('deleted_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('deleted_id');
            $table->json('deleted_data')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();  
            $table->unsignedBigInteger('company_id')->nullable(); 
            $table->string('model_name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deleted_histories');
    }
};
