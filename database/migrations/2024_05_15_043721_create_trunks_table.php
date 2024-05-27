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
        Schema::create('trunks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->enum('type',['Inbound', 'Outbound'])->nullable(); 
			$table->string('name', 250)->nullable();  
            $table->string('prefix',50)->nullable();    
            $table->string('tech',50)->nullable();
            $table->enum('is_register',['0','1'])->nullable();
            $table->string('ip',30)->nullable();
            $table->string('remove_prefix',50)->nullable();      
            $table->integer('failover')->nullable()->index();
            $table->string('max_use',50)->nullable();
            $table->string('if_max_use',50)->nullable();
            $table->string('username',150)->nullable();
            $table->string('password',150)->nullable();
			$table->tinyInteger('status')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trunks');
    }
};
