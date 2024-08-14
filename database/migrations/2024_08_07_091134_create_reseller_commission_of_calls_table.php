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
        Schema::create('reseller_commission_of_calls', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('reseller_id');
            $table->integer('tfn_number');
            $table->smallInteger('country_id');
            $table->decimal('total_amount', total: 8, places: 2);
            $table->decimal('commission_amount', total: 8, places: 2)->nullable()->default(0);
            $table->string('call_type');
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('reseller_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reseller_commission_of_calls');
    }
};
