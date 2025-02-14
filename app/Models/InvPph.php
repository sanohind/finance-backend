<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvPph extends Model
{
    protected $connection = "mysql";

    protected $primaryKey = "pph_id";

    protected $keyType = 'string';

    protected $table = "inv_pph";

    protected $fillable = [
        'pph_id',
        'pph_description',
        'pph_rate',
    ];
}
