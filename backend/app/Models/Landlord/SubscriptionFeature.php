<?php

namespace App\Models\Landlord;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SubscriptionFeature extends Model
{
    use HasUuid;

    protected $connection = 'landlord';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'subscription_features';

    protected $fillable = [
        'name', 'slug', 'description', 'icon',
        'group_name', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(SubscriptionPlan::class, 'plan_feature', 'feature_id', 'plan_id')
            ->withPivot('is_highlighted', 'sort_order')
            ->withTimestamps();
    }
}
