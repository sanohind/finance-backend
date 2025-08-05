<?php

namespace App\Models\Local;

use Illuminate\Database\Eloquent\Model;

class DnDetail extends Model
{
    protected $connection = "mysql";

    protected $table = "dn_detail";

    protected $fillable = [
        'no_dn',
        'dn_line',
        'actual_receipt_date',
        'receipt_qty',
        'status_desc'
    ];
}
