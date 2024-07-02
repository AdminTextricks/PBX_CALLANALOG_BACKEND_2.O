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
        Schema::create('queue_members', function (Blueprint $table) {
            $table->bigIncrements('uniqueid');
			$table->integer('queue_id')->index();
			$table->integer('membername')->index();
            $table->string('queue_name', 200)->nullable();
            $table->string('interface', 100)->nullable();
            $table->integer('penalty')->default(0);
            $table->integer('paused')->default(0);
            $table->integer('wrapuptime')->default(0);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_members');
    }
};
