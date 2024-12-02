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
        Schema::create('servers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->string('ip')->nullable()->index();
            $table->integer('port')->nullable()->default(5060);
            $table->string('domain')->nullable();
            $table->string('user_name')->nullable();
            $table->string('secret')->nullable();
            $table->string('ami_port')->nullable();
            $table->string('barge_url')->nullable();
            $table->tinyInteger('status')->nullable()->default(1);
            $table->timestamps();            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
