<?php

namespace App\Models\Landlord;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanDiscount extends Model
{
    use HasUuid;

    protected $connection = 'landlord';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'plan_id', 'name', 'code', 'type', 'value',
        'billing_cycle', 'description', 'is_recurring',
        'valid_from', 'valid_until', 'max_uses',
        'current_uses', 'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'is_recurring' => 'boolean',
        'is_active' => 'boolean',
        'max_uses' => 'integer',
        'current_uses' => 'integer',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'discount_id');
    }

    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        if ($this->max_uses && $this->current_uses >= $this->max_uses) return false;
        if ($this->valid_from && now()->lt($this->valid_from)) return false;
        if ($this->valid_until && now()->gt($this->valid_until)) return false;
        return true;
    }

    public function applyTo(float $amount): float
    {
        if ($this->type === 'percentage') {
            return round($amount - ($amount * $this->value / 100), 2);
        }
        return round(max(0, $amount - $this->value), 2);
    }
}
