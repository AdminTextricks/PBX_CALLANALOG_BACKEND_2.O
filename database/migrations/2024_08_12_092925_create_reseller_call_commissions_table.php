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
        Schema::create('reseller_call_commissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('reseller_id');
			$table->unsignedBigInteger('company_id');
			$table->integer('country_id')->default(0);
			$table->decimal('inbound_call_commission', 10, 2)->default(0);
			$table->decimal('outbound_call_commission', 10, 2)->default(0);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
            $table->foreign('reseller_id')->references('id')->on('users');
			$table->foreign('company_id')->references('id')->on('companies');            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reseller_call_commissions');
    }
};