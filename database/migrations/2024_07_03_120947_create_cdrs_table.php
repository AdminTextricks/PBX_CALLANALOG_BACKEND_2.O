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
        Schema::create('cdrs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('uniqueid', 100)->nullable();
            $table->integer('company_id')->nullable()->index();
            $table->string('account_code',20)->nullable()->index();
            $table->integer('country_id')->nullable();
            $table->dateTime('call_date')->nullable()->index();
            $table->dateTime('call_start_time')->nullable();
            $table->dateTime('answer_time')->nullable();
            $table->dateTime('hangup_time')->nullable();
            //$table->string('context')->nullable();
            $table->string('agent_channel', 200)->nullable();
            $table->string('agent_name', 200)->nullable()->index();
            $table->string('agent_number', 200)->nullable()->index();
            $table->string('duration', 10)->nullable();
            $table->string('billsec', 10)->nullable();
            $table->string('disposition', 100)->nullable()->index();
            $table->string('call_type')->nullable();
            $table->string('Recording',256)->nullable();
            $table->string('caller_num',256)->nullable()->index();
            $table->string('tfn', 100)->nullable()->index();
            $table->string('destination_type', 100)->nullable();
            $table->string('destination', 20)->nullable()->index();
            $table->string('hangup_cause', 20)->nullable();
            $table->string('receive_ip', 100)->nullable();
            $table->string('trunk_name', 20)->nullable();
            $table->string('codec', 20)->nullable();
            $table->string('cost', 20)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cdrs');
    }
};
