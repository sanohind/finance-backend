<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class News extends Model
{
    protected $connection = "mysql";

    protected $primaryKey = "id";

    protected $table = "news";

    protected $fillable = [
        'id',
        'title',
        'description',
        'start_date',
        'end_date',
        'document',
        'created_by',
        'updated_by',
    ];
}
