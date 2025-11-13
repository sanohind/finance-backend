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
        Schema::connection('mysql')->table('news', function (Blueprint $table) {
            $table->dropColumn('description');
            $table->json('carousel_images')->nullable()->after('title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->table('news', function (Blueprint $table) {
            $table->string('description')->nullable();
            $table->dropColumn('carousel_images');
        });
    }
};
