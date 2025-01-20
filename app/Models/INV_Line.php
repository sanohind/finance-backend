<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class INV_Line extends Model
{
    use HasFactory, Notifiable;

    protected $connection = "mysql";

    protected $primaryKey = "inv_line_id";

    protected $table = "inv_line";

    protected $fillable = [
        'inv_no',
    ];

    public function invHeader(): BelongsTo
    {
        return $this->belongsTo(INV_Header::class, 'inv_no', 'inv_no');
    }
}
