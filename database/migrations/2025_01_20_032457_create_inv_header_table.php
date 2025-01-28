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
        Schema::create('inv_header', function (Blueprint $table) {
            $table->string('inv_no', 255)->primary();

            // Foreign key to user
            $table->string('bp_code')->nullable();
            $table->foreign('bp_code')->references('bp_code')->on('user');

            $table->date('inv_date')->nullable();
            $table->string('inv_faktur')->nullable();
            $table->string('inv_supplier')->nullable();
            $table->integer('total_dpp')->nullable();
            $table->integer('tax')->nullable();
            $table->integer('total_amount')->nullable();
            $table->string('status')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_header');
    }
};
