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

            $table->string('bp_id')->nullable();
            $table->string('bp_name')->nullable();
            $table->string('currency')->nullable();
            $table->string('po_type')->nullable();
            $table->string('po_reference')->nullable();
            $table->integer('po_line')->nullable();
            $table->integer('po_sequence')->nullable();
            $table->integer('po_receipt_sequence')->nullable();

            $table->date('actual_receipt_date')->nullable();
            $table->integer('actual_receipt_year')->nullable();
            $table->integer('actual_receipt_period')->nullable();

            $table->string('receipt_no')->nullable();
            $table->string('receipt_line')->nullable();
            $table->string('gr_no')->nullable();
            $table->string('packing_slip')->nullable();

            $table->string('item_no')->nullable();
            $table->string('ics_code')->nullable();
            $table->string('ics_part')->nullable();
            $table->string('part_no')->nullable();
            $table->string('item_desc')->nullable();
            $table->string('item_group')->nullable();
            $table->string('item_type')->nullable();
            $table->string('item_type_desc')->nullable();

            $table->integer('request_qty')->nullable();
            $table->integer('actual_receipt_qty')->nullable();
            $table->integer('approve_qty')->nullable();
            $table->string('unit')->nullable();

            $table->integer('receipt_amount')->nullable();
            $table->integer('receipt_unit_price')->nullable();

            $table->string('is_final_receipt')->default(false);
            $table->string('is_confirmed')->default(false);

            $table->string('inv_doc_no')->nullable();
            $table->date('inv_doc_date')->nullable();
            $table->integer('inv_qty')->nullable();
            $table->integer('inv_amount')->nullable();

            $table->string('inv_supplier_no')->nullable();
            $table->date('inv_due_date')->nullable();

            $table->string('payment_doc')->nullable();
            $table->date('payment_doc_date')->nullable();

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
