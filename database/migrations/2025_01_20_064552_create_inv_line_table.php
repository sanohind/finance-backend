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
        Schema::create('inv_line', function (Blueprint $table) {
            $table->id('inv_line_id');

            // Foreign key to inv_header
            $table->string( 'inv_no',255)->nullable();
            $table->foreign('inv_no')->references('inv_no')->on('inv_header')->onDelete('cascade');

            $table->string('no_dn')->nullable();
            $table->string('dn_line')->nullable();
            $table->string('dn_supplier')->nullable();
            $table->date('dn_create_date')->nullable();
            $table->date('dn_year')->nullable();
            $table->string('dn_period')->nullable();
            $table->date('plan_delivery_date')->nullable();
            $table->date('plan_delivery_time')->nullable();
            $table->string('order_origin')->nullable();
            $table->string('no_order')->nullable();
            $table->string('order_set')->nullable();
            $table->string('order_line')->nullable();
            $table->string('order_seq')->nullable();
            $table->string('part_no')->nullable();
            $table->string( 'item_desc_a')->nullable();
            $table->string( 'item_desc_b')->nullable();
            $table->string( 'supplier_item_no')->nullable();
            $table->string( 'lot_number')->nullable();
            $table->string( 'dn_qty')->nullable();
            $table->integer( 'receipt_qty')->nullable();
            $table->string( 'dn_unit')->nullable();
            $table->string( 'dn_snp')->nullable();
            $table->string( 'reference')->nullable();
            $table->date( 'actual_receipt_date')->nullable();
            $table->date( 'actual_receipt_time')->nullable();
            $table->string( 'status_code')->nullable();
            $table->string( 'status_desc')->nullable();
            $table->string( 'packing_slip')->nullable();
            $table->integer( 'price')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_line');
    }
};
