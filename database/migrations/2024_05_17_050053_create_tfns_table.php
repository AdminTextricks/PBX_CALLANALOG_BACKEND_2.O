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
            // $table->tinyInteger('plan_id')->nullable()->default(0);
            $table->bigInteger('tfn_number')->unique();
            $table->tinyInteger('tfn_provider', )->nullable();
            // $table->tinyInteger('tfn_group_id')->nullable();
            $table->smallInteger('country_id')->nullable();
            // $table->enum('time_condition',['0','1'])->nullable()->default(0);
            // $table->smallInteger('time_condition_id')->nullable()->default(0);
            $table->enum('activated',['0','1'])->default(0);
            $table->enum('reserved',['0','1'])->default(0);
            $table->timestamp('reserveddate')->nullable();
            $table->timestamp('reservedexpirationdate')->nullable();
            // $table->float('monthly_rate')->nullable()->default(0);
            // $table->decimal('connection_charge', total: 8, places: 2)->nullable()->default(0);
            $table->decimal('selling_rate', total: 8, places: 2)->nullable()->default(0);
            $table->smallInteger('aleg_retail_min_duration')->nullable()->default(0);
            $table->smallInteger('aleg_billing_block')->nullable()->default(0);
            $table->timestamp('startingdate')->nullable();
            $table->timestamp('expirationdate')->nullable();
            $table->enum('call_screen_action', ['0', '1'])->nullable()->default(1); 
            // $table->enum('tfn_auth', ['0', '1'])->nullable()->default(0);           
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
