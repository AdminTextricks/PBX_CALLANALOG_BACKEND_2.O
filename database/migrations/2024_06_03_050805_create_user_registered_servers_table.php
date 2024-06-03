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
        Schema::create('user_registered_servers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('server_id')->nullable();
            $table->unsignedBigInteger('company_id');
            $table->string('domain')->nullable();
            $table->string('sip_port')->nullable();
            $table->timestamps();    
            $table->foreign('company_id')->references('id')->on('companies');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_registered_servers');
    }
};
