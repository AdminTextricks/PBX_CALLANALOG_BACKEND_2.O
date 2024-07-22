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
        Schema::create('invoices', function (Blueprint $table) {
            $table->bigIncrements('id');
            // $table->foreignId('user_id')->constrained()->onDelete('cascade')->onUpdate('cascade');
            $table->bigInteger('company_id')->nullable()->default(0);
			$table->string('country_id');
			$table->string('state_id');                    
            $table->string('invoice_id');
            // $table->string('payment_type')->nullable();
            $table->string('invoice_currency')->nullable();
            $table->decimal('invoice_subtotal_amount', total: 8, places: 2)->nullable()->default(0);
            $table->decimal('invoice_amount', total: 8, places: 2)->nullable()->default(0);
            $table->string('payment_status')->default('Unpaid');
            // $table->string('invoice_file')->nullable();
            $table->integer('email_status')->nullable()->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
