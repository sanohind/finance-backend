<?php

namespace App\Models\Local;

use Illuminate\Database\Eloquent\Model;

class PoDetail extends Model
{
    protected $connection = "mysql";

    protected $table = "po_detail";

    protected $fillable = [
        'po_no',
        'planned_receipt_date',
        'po_qty',
        'price',
        'item_code',
        'item_desc_a',
        'bp_part_no',
        'purchase_unit',
        'amount'
    ];
}
