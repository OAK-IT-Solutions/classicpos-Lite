<?php

namespace App\Models\Landlord;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentReferral extends Model
{
    use HasUuid;

    protected $connection = 'landlord';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'agent_id', 'tenant_id',
        'referral_code', 'landing_url', 'ip_address', 'user_agent',
        'clicked_at', 'registered_at', 'trial_started_at', 'converted_at', 'first_payment_at',
        'commission_earned', 'commission_paid',
    ];

    protected $casts = [
        'clicked_at' => 'datetime',
        'registered_at' => 'datetime',
        'trial_started_at' => 'datetime',
        'converted_at' => 'datetime',
        'first_payment_at' => 'datetime',
        'commission_earned' => 'decimal:2',
        'commission_paid' => 'boolean',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isConverted(): bool
    {
        return $this->converted_at !== null;
    }
}
