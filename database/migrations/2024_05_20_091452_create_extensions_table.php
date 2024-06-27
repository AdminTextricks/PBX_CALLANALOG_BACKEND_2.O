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
        Schema::create('extensions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('country_id',10);
            $table->dateTime('startingdate')->nullable();
            $table->dateTime('expirationdate')->nullable();
			$table->unsignedBigInteger('company_id');
            $table->string('name',50)->unique()->index();
            //$table->string('intercom',50)->unique()->index();
            $table->string('account_code',20)->index();            
            $table->string('regexten',20)->index();
            $table->string('amaflags',10)->nullable();
			$table->enum('callgroup',['0','1'])->nullable();
			$table->string('callerid',100)->nullable();
			$table->string('canreinvite',30)->nullable();
			$table->string('context',100)->nullable();
            $table->string('defaultip',30)->nullable();
            $table->string('dtmfmode',50)->nullable();
            $table->string('fromuser',100)->nullable();
            $table->string('fromdomain',100)->nullablet();
            $table->string('host',100)->nullable();
            $table->string('insecure',100)->nullable();
            $table->string('language',100)->nullable();
            $table->enum('mailbox',['0','1'])->nullable();
            $table->string('nat',50)->nullable();
            $table->string('permit',100)->nullable();
            $table->string('deny',100)->nullable();
            $table->string('mask',100)->nullable();                       
            $table->string('pickupgroup',50)->nullable();
            $table->string('port',50)->nullable();
            $table->string('qualify',50)->nullable();
            $table->string('restrictcid',10)->nullable();
            $table->string('rtptimeout',10)->nullable();
            $table->string('rtpholdtimeout',10)->nullable();
            $table->string('secret',100)->nullable();
            $table->string('type',50)->nullable();
            $table->string('username',100)->nullable();
            $table->string('useragent',100)->nullable();
            $table->string('disallow',100)->nullable();
            $table->string('allow',100)->nullable();
            $table->string('musiconhold',100)->nullable();
            $table->integer('regseconds')->nullable()->default(0);
            $table->string('ipaddr',100)->nullable();
            $table->string('cancallforward',50)->nullable();
            $table->string('fullcontact',100)->nullable();
            $table->string('agent_name',150)->nullable();
            $table->enum('barge',['0','1'])->default(0); 
            $table->enum('sip_temp',['WEBRTC','SOFTPHONE'])->nullable();
            $table->string('regserver',100)->nullable();
            $table->string('lastms',100)->nullable();
            $table->string('callbackextension',100)->nullable();
            $table->tinyInteger('status')->default(0);
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extensions');
    }
};
