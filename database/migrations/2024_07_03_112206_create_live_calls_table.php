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
        Schema::create('live_calls', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('company_id')->nullable()->index();
            $table->integer('account_code')->nullable()->index();
            $table->integer('country_id')->nullable();
            $table->string('agent_channel', 200)->nullable();
            $table->string('agent_name', 200)->nullable();
            $table->string('agent_number', 200)->nullable();
            $table->string('uniqueid', 100)->nullable();
            $table->tinyInteger('call_status')->nullable();
            $table->string('call_type')->nullable();
            $table->string('tfn', 100)->nullable()->index();
            $table->string('destination_type', 100)->nullable();
            $table->string('destination', 20)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_calls');
    }
};
