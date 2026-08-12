<?php

namespace App\Models\Landlord;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class OakitPlanService extends Model
{
    use HasUuid;

    protected $connection = 'landlord';
    protected $table = 'oakit_plan_services';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'plan_id', 'service_id', 'is_included', 'custom_limit',
    ];

    protected $casts = [
        'is_included' => 'boolean',
        'custom_limit' => 'integer',
    ];

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function service()
    {
        return $this->belongsTo(OakitService::class, 'service_id');
    }
}
