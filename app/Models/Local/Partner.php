<?php

namespace App\Models\Local;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\InvLine;

class Partner extends Model
{
    use HasFactory;

    protected $connection = "mysql2";

    protected $table = "business_partner";

    protected $primaryKey = "bp_code";

    protected $keyType = "string";

    protected $fillable = [
        'bp_code',
        'parent_bp_code',
        'bp_name',
        'bp_address',
        'bp_email',
    ];

    public function invLine(): HasMany
    {
        return $this->hasMany(InvLine::class, 'bp_id', 'bp_code');
    }

    // Accessor untuk base bp_code (tanpa akhiran)
    public function getBaseBpCodeAttribute()
    {
        return preg_replace('/-\d+$/', '', $this->bp_code);
    }

    // Scope untuk query bp_code parent & child
    public function scopeRelatedBpCodes($query, $bpCode)
    {
        $base = preg_replace('/-\d+$/', '', $bpCode);
        return $query->where('bp_code', 'like', $base . '%');
    }

    public function isParentRecord()
    {
        return is_null($this->parent_bp_code);
    }

    public function isChildRecord()
    {
        return !is_null($this->parent_bp_code);
    }
}