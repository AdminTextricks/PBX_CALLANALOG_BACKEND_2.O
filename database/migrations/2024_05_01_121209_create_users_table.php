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
        Schema::create('users', function (Blueprint $table) {
            //$table->id();            
			$table->bigIncrements('id');
			$table->unsignedBigInteger('company_id')->nullable();
			//$table->string('account_code',50)->index();
            $table->string('name');
            $table->string('email')->index()->unique();
			$table->string('mobile',50)->index()->unique();
			$table->text('address');
			$table->string('country_id',50);
			$table->string('state_id',50);
			$table->string('city',50);
			$table->string('zip',50);
            $table->timestamp('email_verified_at')->nullable();
            $table->integer('is_verified')->default(0);
			$table->integer('is_verified_doc')->default(0);
			$table->rememberToken();
			$table->string('password',200);
			$table->string('role_id',5)->nullable();
            //$table->string('plan_id',5)->nullable();
			$table->string('timezones')->nullable();			
			$table->tinyInteger('status')->default(0);
            $table->timestamps();
			//$table->foreign('company_id')->references('id')->on('companies');
			//$table->foreign('company_id')->references('id')->on('companies')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
