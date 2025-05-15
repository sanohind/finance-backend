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
        Schema::create('inv_pph', function (Blueprint $table) {
            $table->id('pph_id');
            $table->string('pph_description', 255)->nullable();
            $table->decimal('pph_rate', 8, 4)->nullable(); // Ensures DECIMAL(8,4)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_pph');
    }
};
