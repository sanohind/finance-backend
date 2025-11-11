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
        Schema::connection('mysql')->table('inv_line', function (Blueprint $table) {
          
            $table->decimal('request_qty', 15, 4)->nullable()->change();
            $table->decimal('actual_receipt_qty', 15, 4)->nullable()->change();
            $table->decimal('approve_qty', 15, 4)->nullable()->change();
            
            
            $table->decimal('receipt_amount', 15, 2)->nullable()->change();
            $table->decimal('receipt_unit_price', 15, 2)->nullable()->change();
            $table->decimal('inv_qty', 15, 4)->nullable()->change();
            $table->decimal('inv_amount', 15, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->table('inv_line', function (Blueprint $table) {
            // Revert back to integer (data loss warning!)
            $table->integer('request_qty')->nullable()->change();
            $table->integer('actual_receipt_qty')->nullable()->change();
            $table->integer('approve_qty')->nullable()->change();
            $table->integer('receipt_amount')->nullable()->change();
            $table->integer('receipt_unit_price')->nullable()->change();
            $table->integer('inv_qty')->nullable()->change();
            $table->integer('inv_amount')->nullable()->change();
        });
    }
};
