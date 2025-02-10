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
        Schema::create('inv_document', function (Blueprint $table) {
            $table->id('inv_doc_id');

            // Foreign key to inv_header
            $table->string('inv_no', 255)->nullable();
            $table->foreign('inv_no')->references('inv_no')->on('inv_header')->onDelete('cascade');

            $table->string('file', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_document');
    }
};
