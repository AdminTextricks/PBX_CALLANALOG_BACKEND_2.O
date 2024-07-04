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
        Schema::create('conferences', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->integer('country_id')->index()->default(0);
            $table->string('conf_name', 256)->nullable();
            $table->string('confno', 100)->nullable()->index();
            //$table->dateTime('starttime')->nullable();
            //$table->dateTime('endtime')->nullable();
            $table->integer('pin')->nullable();
            $table->string('adminpin',100)->nullable();
            $table->string('opts',100)->nullable();
            $table->string('adminopts',100)->nullable();
            $table->integer('maxusers')->default(10);
            $table->integer('members')->nullable();
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
        Schema::dropIfExists('conferences');
    }
};
