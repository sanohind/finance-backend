<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\InvPph;
use App\Models\InvPpn;

class InvHeader extends Model
{
    use HasFactory, Notifiable;

    protected $connection = "mysql";

    protected $primaryKey = "inv_id";

    protected $keyType = 'integer';

    protected $table = "inv_header";

    protected $fillable = [
        'inv_id',
        'inv_no',
        'receipt_number',
        'receipt_path',
        'bp_code',
        'inv_date',
        'plan_date',
        'actual_date',
        'inv_faktur',
        'inv_faktur_date',
        'total_dpp',
        'ppn_id',
        'tax_base_amount',
        'tax_amount',
        'pph_id',
        'pph_base_amount',
        'pph_amount',
        'created_by',
        'updated_by',
        'total_amount',
        'status',
        'reason',
    ];

    public function invLine(): BelongsToMany
    {
        return $this->belongsToMany(InvLine::class, 'transaction_invoice', 'inv_id', 'inv_line_id')
            ->withTimestamps();
    }

    public function invDocument(): HasMany
    {
        return $this->hasMany(InvDocument::class, 'inv_id', 'inv_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bp_code', 'bp_code');
    }

    public function invPpn(): BelongsTo
    {
        return $this->belongsTo(InvPpn::class, 'ppn_id', 'ppn_id');
    }

    public function invPph(): BelongsTo
    {
        return $this->belongsTo(InvPph::class, 'pph_id', 'pph_id');
    }
}
