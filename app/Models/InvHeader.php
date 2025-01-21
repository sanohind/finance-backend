<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvHeader extends Model
{
    use HasFactory, Notifiable;

    protected $connection = "mysql";

    protected $primaryKey = "inv_no";

    protected $keyType = 'string';

    protected $table = "inv_header";

    protected $fillable = [
        'inv_no',
        'inv_date',
        'inv_faktur',
        'inv_supplier',
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
}
