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
        Schema::create('tfn_authentications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tfn_id');
            $table->string('authentication_type', 20)->nullable();
            $table->string('auth_digit', 20)->nullable();
            $table->timestamps();
            $table->foreign('tfn_id')->references('id')->on('tfns');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tfn_authentications');
    }
};
