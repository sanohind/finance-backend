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
        'supplier_id',
        'supplier',
        'po_date',
        'po_qty',
        'po_price',
        'currency',
        'rate',
        'receipt_no',
        'receipt_date',
        'receipt_line',
        'item',
        'item_desc',
        'old_partno',
        'receipt_qty',
        'receipt_unit',
        'packing_slip',
        'receipt_status',
        'warehouse',
        'extend_price',
        'extend_price_idr',
        'inv_doc',
        'inv_date',
        'supplier_invoice',
        'supplier_invoice_date',
        'doc_code',
        'doc_no',
        'doc_date',
    ];

    // protected $fillable = [
    //     'supplier_invoice',
    //     'supplier_invoice_date',
    // ];

    public function invHeader(): BelongsTo
    {
        return $this->belongsTo(InvHeader::class, 'inv_no', 'inv_no');
    }
}
