<?php

namespace App\Models\ERP;

use Illuminate\Database\Eloquent\Model;

class InvReceipt extends Model
{
    protected $connection = "sqlsrv";

    protected $table = "data_receipt_purchase";
}
