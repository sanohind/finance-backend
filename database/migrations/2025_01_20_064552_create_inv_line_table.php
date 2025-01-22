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


            $table->string('po_no')->nullable();
            $table->string('supplier_id')->nullable();
            $table->string('supplier')->nullable();
            $table->date('po_date')->nullable();
            $table->integer('po_qty')->nullable();
            $table->integer('po_price')->nullable();
            $table->string('currency')->nullable();
            $table->float('rate')->nullable();
            $table->string('receipt_no')->nullable();
            $table->date('receipt_date')->nullable();
            $table->string('receipt_line')->nullable();
            $table->string('item')->nullable();
            $table->string('item_desc')->nullable();
            $table->string('old_partno')->nullable();
            $table->integer('receipt_qty')->nullable();
            $table->string('receipt_unit')->nullable();
            $table->string('packing_slip')->nullable();
            $table->string('receipt_status')->nullable();
            $table->string('warehouse')->nullable();
            $table->integer('extend_price')->nullable();
            $table->integer('extend_price_idr')->nullable();

            $table->string('inv_doc')->nullable();
            // Tenggat Tanggal Pembayaran
            $table->string('inv_date')->nullable();

            // Foreign key to inv_header
            $table->string( 'supplier_invoice',255)->nullable();
            $table->foreign('supplier_invoice')->references('inv_no')->on('inv_header')->onDelete('cascade');

            // inv_date dibuat
            $table->string('supplier_invoice_date')->nullable();

            $table->string('doc_code')->nullable();
            $table->string('doc_no')->nullable();
            $table->string('doc_date')->nullable();
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
