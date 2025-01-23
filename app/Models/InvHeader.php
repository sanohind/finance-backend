<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvHeader extends Model
{
    use HasFactory, Notifiable;

    protected $connection = "mysql";

    protected $primaryKey = "inv_no";

    protected $keyType = 'string';

    protected $table = "inv_header";

    protected $fillable = [
        'inv_no',
        'bp_code',
        'inv_date',
        'inv_faktur',
        'inv_supplier',
        'total_dpp',
        'tax',
        'total_amount',
        'status',
        'reason',
    ];

    public function invLine(): HasMany
    {
        return $this->hasMany(InvLine::class, 'inv_no', 'inv_no');
    }

    public function invDocument(): HasMany
    {
        return $this->hasMany(InvDocument::class, 'inv_no', 'inv_no');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bp_code', 'bp_code');
    }
}
