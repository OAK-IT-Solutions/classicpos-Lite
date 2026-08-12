<?php

namespace App\Models\Landlord;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OakitService extends Model
{
    use HasUuid;

    protected $connection = 'landlord';
    protected $table = 'oakit_services';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'slug', 'title', 'description', 'icon',
        'features', 'benefits', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'features' => 'array',
        'benefits' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(SubscriptionPlan::class, 'oakit_plan_services', 'service_id', 'plan_id')
            ->withPivot('is_included', 'custom_limit');
    }
}
