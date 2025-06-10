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
        Schema::connection('mysql')->create('transaction_invoice', function (Blueprint $table) {
            $table->unsignedBigInteger('inv_id')->nullable();
            $table->foreign('inv_id')->references('inv_id')->on('inv_header')->onDelete('cascade');
            $table->unsignedBigInteger('inv_line_id')->nullable();
            $table->foreign('inv_line_id')->references('inv_line_id')->on('inv_line')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_invoice');
    }
};
