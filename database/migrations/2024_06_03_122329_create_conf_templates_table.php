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
        Schema::create('conf_templates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('template_id',100)->nullable();
            $table->string('template_name',100)->nullable();
            $table->text('template_contents')->nullable();
            $table->string('user_group',50)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conf_templates');
    }
};
