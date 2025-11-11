<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Local\Partner;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    /**
     * Type casting untuk handle decimal dengan benar
     * Quantity: 4 decimal places untuk support fractional (0.25, 0.5, etc)
     * Amount: 2 decimal places untuk currency standard
     */
    protected $casts = [
        'request_qty' => 'decimal:4',
        'actual_receipt_qty' => 'decimal:4',
        'approve_qty' => 'decimal:4',
        'receipt_amount' => 'decimal:2',
        'receipt_unit_price' => 'decimal:2',
        'inv_qty' => 'decimal:4',
        'inv_amount' => 'decimal:2',
    ];

    public function invHeader(): BelongsToMany
    {
        return $this->belongsToMany(InvHeader::class, 'transaction_invoice', 'inv_line_id', 'inv_id')
            ->withTimestamps();
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'bp_id', 'bp_code');
    }
}
