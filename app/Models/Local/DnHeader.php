<?php

namespace App\Models\Local;

use Illuminate\Database\Eloquent\Model;

class DnHeader extends Model
{
    protected $connection = "mysql2";

    protected $table = "dn_header";
}
