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
        Schema::create('ring_groups', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('country_id',100)->index()->nullable()->default(0);
            $table->unsignedBigInteger('company_id');
            $table->string('ringno',150)->nullable();
            $table->string('strategy',150)->nullable();
            $table->string('ringtime',50)->nullable();
            $table->string('description',250)->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ring_groups');
    }
};
