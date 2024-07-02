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
        Schema::create('queues', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->integer('country_id')->index()->default(0);
            $table->integer('name')->index()->comment('Asterisks name for the queue');
            $table->string('queue_name', 200)->nullable();
            $table->string('description', 256)->nullable();
            $table->string('musiconhold', 150)->default('default');
            $table->string('announce', 50)->default('no')->nullable();
            $table->string('context', 100)->default('default');
            $table->integer('timeout')->default(0);
            $table->integer('monitor_join')->default(0);
            $table->string('monitor_format', 100)->default('WAV');
            $table->string('queue_youarenext', 50)->nullable();
            $table->integer('queue_thereare')->default(0);
            $table->integer('queue_callswaiting')->default(0)->nullable();
            $table->integer('queue_holdtime')->default(0)->nullable();
            $table->integer('queue_minutes')->default(0)->nullable();
            $table->integer('queue_seconds')->default(0)->nullable();
            $table->integer('queue_lessthan')->default(0)->nullable();
            $table->string('queue_thankyou', 50)->default('yes');
            $table->string('queue_reporthold', 50)->default('yes')->nullable();
            $table->integer('announce_frequency')->default(0);
            $table->string('announce_round_seconds', 50)->nullable();
            $table->string('announce_holdtime', 50)->nullable();
            $table->integer('retry')->default(0);
            $table->integer('wrapuptime')->default(0);
            $table->integer('maxlen')->default(0);
            $table->integer('servicelevel')->default(60);
            $table->string('strategy', 150)->nullable();
            $table->string('joinempty', 50)->default('no');
            $table->string('leavewhenempty', 50)->default('no');
            $table->tinyInteger('eventmemberstatus')->default(0);
            $table->tinyInteger('eventwhencalled')->default(0);
            $table->string('reportholdtime', 50)->default('no');
            $table->integer('memberdelay')->default(0);
            $table->integer('weight')->default(0);
            $table->integer('timeoutrestart')->default(0)->nullable();
            $table->string('periodic_announce', 50)->default('default');
            $table->string('periodic_announce_frequency', 50)->default('no');
            $table->string('ringinuse', 50)->default('no');
            $table->string('setinterfacevar', 50)->default('no');
            $table->string('monitor_type', 100)->default('MixMonitor'); 
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
        Schema::dropIfExists('queues');
    }
};
