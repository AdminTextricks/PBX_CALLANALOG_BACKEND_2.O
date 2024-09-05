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
        Schema::create('tfn_import_csv_lists', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->tinyInteger('uploaded_by');
            $table->string('tfn_import_csv');
            $table->tinyInteger('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tfn_import_csv_lists');
    }
};
