<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessProfile extends Model
{
    use HasUuid;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'branch_id', 'legal_business_name', 'trading_name', 'business_type',
        'tax_id', 'vat_registered', 'currency', 'country', 'timezone',
        'address_line1', 'address_line2', 'city', 'state_province', 'postal_code',
        'phone', 'email', 'website', 'logo_url', 'registration_number',
        'established_year', 'description', 'settings', 'onboarding_completed',
    ];

    protected $casts = [
        'vat_registered' => 'boolean',
        'onboarding_completed' => 'boolean',
        'established_year' => 'integer',
        'settings' => 'json',
        'created_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
