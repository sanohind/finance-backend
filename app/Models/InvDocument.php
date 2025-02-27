<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvDocument extends Model
{
    use HasFactory, Notifiable;

    protected $connection = "mysql";

    protected $primaryKey = "inv_doc_id";

    protected $table = "inv_document";

    protected $fillable = [
        'inv_no',
        'file',
        'type',
    ];

    public function invHeader(): BelongsTo
    {
        return $this->belongsTo(InvHeader::class, 'inv_no', 'inv_no');
    }
}
