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
        Schema::create('block_numbers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->string('digits');
            $table->string('transfer_number')->nullable();
            $table->enum('subject', ['prefix', 'phonenumber']);
            $table->enum('ruletype', ['transfer', 'block']);
            $table->enum('blocktype', ['busy', 'congestion','hangup'])->nullable();
            $table->tinyInteger('status')->nullable()->default(1);
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('block_numbers');
    }
};
