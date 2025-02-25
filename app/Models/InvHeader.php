<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\InvPph;
use App\Models\InvPpn;

class InvHeader extends Model
{
    use HasFactory, Notifiable;

    protected $connection = "mysql";

    protected $primaryKey = "inv_no";

    protected $keyType = 'string';

    protected $table = "inv_header";

    protected $fillable = [
        'inv_no',
        'receipt_number',
        'receipt_path',
        'bp_code',
        'inv_date',
        'plan_date',
        'actual_date',
        'inv_faktur',
        'inv_faktur_date',
        'inv_supplier',
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

    public function invLine(): HasMany
    {
        return $this->hasMany(InvLine::class, 'inv_supplier_no', 'inv_no');
    }

    public function invDocument(): HasMany
    {
        return $this->hasMany(InvDocument::class, 'inv_no', 'inv_no');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bp_code', 'bp_code');
    }

    public function invPpn(): BelongsTo
    {
        return $this->belongsTo(InvPpn::class, 'tax', 'tax_id');
    }

    public function invPph(): BelongsTo
    {
        return $this->belongsTo(InvPph::class, 'pph', 'pph_id');
    }
}
