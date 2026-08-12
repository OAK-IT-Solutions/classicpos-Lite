<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class LoyaltyRule extends Model
{
    use HasUuid;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'points_per_amount',
        'points_earned',
        'signup_bonus_points',
        'member_levels',
        'reward_thresholds',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'member_levels' => 'array',
        'reward_thresholds' => 'array',
    ];
}
