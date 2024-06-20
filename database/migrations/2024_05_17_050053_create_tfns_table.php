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
        Schema::create('tfns', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('company_id')->index()->nullable()->default(0);
            $table->integer('assign_by')->nullable()->default(0);
            $table->bigInteger('plan_id')->nullable();
            $table->bigInteger('tfn_number')->unique();
            $table->string('tfn_provider')->nullable();
            $table->bigInteger('tfn_group_id')->nullable();
            $table->bigInteger('country_id')->nullable();
            $table->bigInteger('tfn_type_id')->nullable();
            $table->string('tfn_type_number')->nullable();
            $table->bigInteger('activated')->default(0);
            $table->bigInteger('reserved')->default(0);
            $table->timestamp('reserveddate')->nullable();
            $table->timestamp('reservedexpirationdate')->nullable();
            $table->float('monthly_rate')->nullable()->default(0);
            $table->decimal('connection_charge', total: 8, places: 2)->nullable()->default(0);
            $table->decimal('selling_rate', total: 8, places: 2)->nullable()->default(0);
            $table->bigInteger('aleg_retail_min_duration')->nullable()->default(0);
            $table->bigInteger('aleg_billing_block')->nullable()->default(0);
            $table->timestamp('startingdate')->nullable();
            $table->timestamp('expirationdate')->nullable();
            $table->tinyInteger('status')->default(0);
            $table->timestamps();
            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tfns');
    }
};
