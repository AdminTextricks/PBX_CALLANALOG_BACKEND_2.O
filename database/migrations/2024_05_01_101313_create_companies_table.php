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
        Schema::create('companies', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->integer('parent_id')->nullable()->default(0);
            $table->string('company_name',150);
            $table->string('email',50)->index()->unique();
            $table->string('mobile',150)->index()->unique();
            $table->string('billing_address');
			$table->string('country_id',10);
			$table->string('state_id',10);
			$table->string('city',150);
			$table->string('zip',50);
            $table->string('plan_id',5)->nullable();
            $table->decimal('balance',10, 2)->default(0);
            $table->tinyInteger('status')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
