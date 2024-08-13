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
        Schema::create('one_go_user_steps', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
			$table->unsignedBigInteger('user_id');
            $table->integer('country_id')->index()->nullable();
			$table->integer('tfn_id')->nullable();
			$table->string('extension_id',100)->nullable();
			$table->integer('ring_id')->nullable();
			$table->integer('invoice_id')->nullable();
			$table->integer('payment_id')->nullable();
			$table->tinyInteger('step_no')->default(0);
            $table->timestamps();
			$table->foreign('company_id')->references('id')->on('companies');
			$table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('one_go_user_steps');
    }
};
