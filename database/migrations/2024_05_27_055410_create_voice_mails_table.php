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
        Schema::create('voice_mails', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->string('context',50)->default('default');
            $table->string('mailbox',20)->default('0');
            $table->string('password',20)->default('0');
            $table->string('fullname',150)->nullable();
            $table->string('email',250)->nullable();
            $table->string('pager',50)->nullable();
            $table->string('timezone',100)->default('central');
            $table->string('attach',10)->default('yes');
            $table->string('saycid',10)->default('yes');
            $table->string('dialout',20)->nullable();
            $table->string('callback',20)->nullable();
            $table->string('review',20)->default('no');
            $table->string('operator',20)->default('no');
            $table->string('envelope',20)->default('no');
            $table->string('sayduration',20)->default('no');
            $table->string('saydurationm',4)->default('1');
            $table->string('sendvoicemail',20)->default('no');
            $table->string('nextaftercmd',20)->default('yes');
            $table->string('forcename',20)->default('no');
            $table->string('forcegreetings',20)->default('no');
            $table->string('hidefromdir',20)->default('yes');
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voice_mails');
    }
};
