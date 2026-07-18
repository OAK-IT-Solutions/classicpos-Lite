<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Integration extends Model
{
    use HasFactory, HasUuid;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'branch_id',
        'type',
        'name',
        'status',
        'config',
        'last_sync_at',
        'last_error',
    ];

    protected $casts = [
        'config' => 'encrypted:array',
        'last_sync_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function efrisConfig(): HasOne
    {
        return $this->hasOne(EfrisConfig::class);
    }

    public function fiscalLogs()
    {
        return $this->hasManyThrough(EfrisFiscalLog::class, EfrisConfig::class, 'integration_id', 'branch_id', 'id', 'branch_id');
    }

    public function scopeForBranch($query, string $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
