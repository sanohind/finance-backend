<?php

namespace App\Models\Local;

use Illuminate\Database\Eloquent\Model;

class PoDetail extends Model
{
    protected $connection = "mysql2";

    protected $table = "po_detail";
}
