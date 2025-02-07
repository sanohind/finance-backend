<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvLine extends Model
{
    use HasFactory, Notifiable;

    protected $connection = "mysql";

    protected $primaryKey = "inv_line_id";

    protected $table = "inv_line";

    protected $fillable = [
        'po_no',
        'bp_id',
        'bp_name',
        'currency',
        'po_type',
        'po_reference',
        'po_line',
        'po_sequence',
        'po_receipt_sequence',
        'actual_receipt_date',
        'actual_receipt_year',
        'actual_receipt_period',
        'receipt_no',
        'receipt_line',
        'gr_no',
        'packing_slip',
        'item_no',
        'ics_code',
        'ics_part',
        'part_no',
        'item_desc',
        'item_group',
        'item_type',
        'item_type_desc',
        'request_qty',
        'actual_receipt_qty',
        'approve_qty',
        'unit',
        'receipt_amount',
        'receipt_unit_price',
        'is_final_receipt',
        'is_confirmed',
        'inv_doc_no',
        'inv_doc_date',
        'inv_qty',
        'inv_amount',
        'inv_supplier_no',
        'inv_due_date',
        'payment_doc',
        'payment_doc_date',
    ];

    public function invHeader(): BelongsTo
    {
        return $this->belongsTo(InvHeader::class, 'inv_supplier_no', 'inv_no');
    }
}
