<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DesktopLicense extends Model
{
    use HasUuid, HasFactory;

    protected $connection = 'sqlite';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'business_name', 'email', 'license_key', 'plan', 'amount', 'currency',
        'payment_method', 'payment_reference', 'status', 'activated_at', 'expires_at',
        'device_fingerprint', 'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'activated_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'json',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_VOIDED = 'voided';

    // Plan constants
    const PLAN_PROFESSIONAL = 'professional';
    const PLAN_ENTERPRISE = 'enterprise';

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && !$this->isExpired();
    }

    public function getFeatures(): array
    {
        return match ($this->plan) {
            self::PLAN_ENTERPRISE => [
                'full_pos', 'reports', 'multi_branch', 'custom_integrations',
                'priority_support', 'lifetime_updates',
            ],
            default => [
                'full_pos', 'reports', 'multi_branch',
                'usb_printing', 'cash_drawer', 'auto_updates',
            ],
        };
    }

    public function getUpdatesExpiry(): ?string
    {
        return match ($this->plan) {
            self::PLAN_ENTERPRISE => 'Lifetime',
            default => $this->expires_at ? $this->expires_at->format('F j, Y') : '1 year from purchase',
        };
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }
}
