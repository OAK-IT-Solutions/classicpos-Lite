<?php

namespace App\Models\Landlord;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Agent extends Model
{
    use HasUuid, SoftDeletes;

    protected $connection = 'landlord';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id', 'code', 'name', 'email', 'phone',
        'commission_rate', 'tier', 'tier_threshold',
        'is_active', 'activated_at',
        'total_referrals', 'converted_referrals',
        'total_earnings', 'pending_earnings', 'paid_earnings',
        'metadata',
    ];

    protected $appends = ['conversion_rate'];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'tier_threshold' => 'decimal:2',
        'is_active' => 'boolean',
        'activated_at' => 'datetime',
        'total_referrals' => 'integer',
        'converted_referrals' => 'integer',
        'total_earnings' => 'decimal:2',
        'pending_earnings' => 'decimal:2',
        'paid_earnings' => 'decimal:2',
        'metadata' => 'array',
    ];

    // --- Relationships ---

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(AgentReferral::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AgentCommission::class);
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function performance(): HasMany
    {
        return $this->referrals();
    }

    // --- Helpers ---

    public function getConversionRateAttribute(): float
    {
        if (!$this->total_referrals) return 0;
        return round(($this->converted_referrals / $this->total_referrals) * 100, 1);
    }

    public function tierLabel(): string
    {
        return match($this->tier) {
            'platinum' => 'Platinum Agent',
            'gold' => 'Gold Agent',
            'silver' => 'Silver Agent',
            default => 'Standard Agent',
        };
    }
}
