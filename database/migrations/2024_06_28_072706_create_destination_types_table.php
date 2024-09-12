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
        Schema::create('destination_types', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('destination_type');
            $table->tinyInteger('status');
            $table->enum('type', ['dropdown', 'textfield'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('destination_types');
    }
};
