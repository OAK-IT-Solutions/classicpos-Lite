<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use App\Traits\AuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory, HasUuid, SoftDeletes;
    use AuditLog;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'branch_id',
        'phone',
        'email',
        'name',
        'location',
        'loyalty_points',
        'member_level',
    ];

    protected $casts = [
        'loyalty_points' => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function holdSales(): HasMany
    {
        return $this->hasMany(HoldSale::class);
    }

    public function getTotalSpendAttribute(): float
    {
        return (float) $this->sales()
            ->where('status', 'completed')
            ->sum('total_amount');
    }

    public function getTotalVisitsAttribute(): int
    {
        return $this->sales()
            ->where('status', 'completed')
            ->count();
    }

    public function getAvgOrderValueAttribute(): float
    {
        $total = $this->total_spend;
        $visits = $this->total_visits;
        return $visits > 0 ? $total / $visits : 0;
    }

    public function getLastPurchaseDateAttribute()
    {
        $lastSale = $this->sales()
            ->where('status', 'completed')
            ->latest('created_at')
            ->first();

        return $lastSale?->created_at;
    }
}
