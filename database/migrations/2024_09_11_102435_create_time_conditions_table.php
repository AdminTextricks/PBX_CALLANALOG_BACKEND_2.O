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
        Schema::create('time_conditions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->smallInteger('country_id');
            $table->string('name', 200)->nullable()->index();
            $table->string('time_zone', 200)->nullable();
            $table->unsignedBigInteger('time_group_id');
            $table->string('tc_match_destination_type')->nullable();
            $table->string('tc_match_destination_id')->nullable();
            $table->string('tc_non_match_destination_type')->nullable();
            $table->string('tc_non_match_destination_id')->nullable();
            $table->tinyInteger('status')->nullable()->default(1);
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('time_group_id')->references('id')->on('time_groups');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_conditions');
    }
};
