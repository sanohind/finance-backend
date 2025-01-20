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
            $table->string( 'inv_no',255);
            $table->foreign('inv_no')->references('inv_no')->on('inv_header')->onDelete('cascade');

            $table->string('no_dn');
            $table->string('dn_line');
            $table->string('dn_supplier');
            $table->date('dn_create_date');
            $table->date('dn_year');
            $table->string('dn_period');
            $table->date('plan_delivery_date');
            $table->date('plan_delivery_time');
            $table->string('order_origin');
            $table->string('no_order');
            $table->string('order_set');
            $table->string('order_line');
            $table->string('order_seq');
            $table->string('part_no');
            $table->string( 'item_desc_a');
            $table->string( 'item_desc_b');
            $table->string( 'supplier_item_no');
            $table->string( 'lot_number');
            $table->string( 'dn_qty');
            $table->integer( 'receipt_qty');
            $table->string( 'dn_unit');
            $table->string( 'dn_snp');
            $table->string( 'reference');
            $table->date( 'actual_receipt_date');
            $table->date( 'actual_receipt_time');
            $table->string( 'status_code');
            $table->string( 'status_desc');
            $table->string( 'packing_slip');
            $table->integer( 'price');
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
