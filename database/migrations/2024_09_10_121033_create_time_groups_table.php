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
        Schema::create('time_groups', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->string('name', 200)->nullable()->index();
            $table->time('time_to_start')->nullable();
            $table->time('time_to_finish')->nullable();
            $table->string('week_day_start', 50)->nullable();
            $table->string('week_day_finish', 50)->nullable();
            $table->string('month_day_start', 50)->nullable();
            $table->string('month_day_finish', 50)->nullable();
            $table->string('month_start', 50)->nullable();
            $table->string('month_finish', 50)->nullable();
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_groups');
    }
};
