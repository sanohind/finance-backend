<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvPpn extends Model
{
    protected $connection = "mysql";

    protected $primaryKey = "ppn_id";

    protected $keyType = 'string';

    protected $table = "inv_ppn";

    protected $fillable = [
        'ppn_id',
        'ppn_description',
        'ppn_rate',
    ];
}
