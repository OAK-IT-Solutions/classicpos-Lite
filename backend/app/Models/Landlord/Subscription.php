<?php

namespace App\Models\Landlord;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasUuid;

    protected $connection = 'landlord';
    protected $table = 'tenant_subscriptions';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id', 'client_user_id', 'plan_id', 'subscription_plan_id',
        'status', 'billing_cycle',
        'amount', 'original_amount', 'discount_id', 'discount_percent',
        'starts_at', 'ends_at', 'trial_ends_at', 'cancelled_at', 'past_due_at',
        'pesapal_subscription_id', 'paypal_order_id', 'paypal_subscription_id', 'metadata',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'past_due_at' => 'datetime',
        'amount' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function clientUser(): BelongsTo
    {
        return $this->belongsTo(ClientUser::class, 'client_user_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(PlanDiscount::class, 'discount_id');
    }

    public function paymentTransactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isTrial(): bool
    {
        return $this->status === 'trialing';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' || ($this->ends_at && $this->ends_at->isPast());
    }

    public function daysUntilRenewal(): int
    {
        return $this->ends_at ? max(0, now()->diffInDays($this->ends_at, false)) : 0;
    }
}
