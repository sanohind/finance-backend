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
        Schema::connection('mysql')->create('user', function (Blueprint $table) {
            $table->id('user_id');

            // Foreign key column bp_code from table bussiness_partner
            $table->string('bp_code',25);
            $table->index('bp_code');

            $table->string('name',255)->nullable();
            $table->string('role',25)->nullable();
            $table->integer('status')->nullable();
            $table->string('username',25)->nullable();
            $table->string('password',255)->nullable();

            // tambahan email
            $table->string('email',255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user');
    }
};
