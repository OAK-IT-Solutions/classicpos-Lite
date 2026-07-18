<?php

namespace App\Models\Landlord;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasUuid;

    protected $connection = 'landlord';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name', 'slug', 'type', 'description',
        'price_monthly', 'price_yearly', 'discount_percent_yearly',
        'max_branches', 'max_users_per_branch', 'max_devices_per_branch',
        'features', 'is_active', 'is_default', 'sort_order',
        'is_popular', 'highlight_color', 'cta_text',
    ];

    protected $casts = [
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'discount_percent_yearly' => 'decimal:2',
        'max_branches' => 'integer',
        'max_users_per_branch' => 'integer',
        'max_devices_per_branch' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'is_popular' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    public function planFeatures(): BelongsToMany
    {
        return $this->belongsToMany(SubscriptionFeature::class, 'plan_feature', 'plan_id', 'feature_id')
            ->withPivot('is_highlighted', 'sort_order')
            ->orderBy('sort_order');
    }

    public function oakitServices(): BelongsToMany
    {
        return $this->belongsToMany(OakitService::class, 'oakit_plan_services', 'plan_id', 'service_id')
            ->withPivot('is_included', 'custom_limit');
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(PlanDiscount::class, 'plan_id');
    }

    public function activeDiscounts(): HasMany
    {
        return $this->hasMany(PlanDiscount::class, 'plan_id')->where('is_active', true);
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    public function getPriceForCycle(string $cycle): float
    {
        if ($cycle === 'yearly') {
            $price = (float) $this->price_yearly;
            if ($this->discount_percent_yearly) {
                $price = $price - ($price * $this->discount_percent_yearly / 100);
            }
            return round($price, 2);
        }
        return (float) $this->price_monthly;
    }

    public function getSavingsPercent(): float
    {
        $monthly = (float) $this->price_monthly;
        $yearly = (float) $this->price_yearly;
        if ($monthly <= 0 || $yearly <= 0) return 0;
        $monthlyYearly = $monthly * 12;
        return round((1 - $yearly / $monthlyYearly) * 100, 1);
    }

    public function getEffectiveYearlyPrice(): float
    {
        return $this->getPriceForCycle('yearly');
    }
}
