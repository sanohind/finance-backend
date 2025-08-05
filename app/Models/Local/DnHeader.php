<?php

namespace App\Models\Local;

use Illuminate\Database\Eloquent\Model;

class DnHeader extends Model
{
    protected $connection = "mysql";

    protected $table = "dn_header";

    protected $fillable = [
        'po_no',
        'no_dn',
        'supplier_code',
        'supplier_name'
    ];
}
