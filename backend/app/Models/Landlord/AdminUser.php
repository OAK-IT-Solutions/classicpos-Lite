<?php

namespace App\Models\Landlord;

use App\Models\Traits\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\Sanctum;

class AdminUser extends User
{
    use HasUuid, HasApiTokens;

    protected $connection = 'landlord';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name', 'email', 'password', 'role', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    public function tokens(): MorphMany
    {
        return $this->morphMany(Sanctum::personalAccessTokenModel(), 'tokenable');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isSupport(): bool
    {
        return $this->role === 'support';
    }

    public function canManage(string $section): bool
    {
        return match($this->role) {
            'super_admin' => true,
            'admin' => in_array($section, ['tenants', 'plans', 'subscriptions', 'revenue', 'agents', 'commissions', 'tickets', 'audit_log', 'health', 'settings', 'admin_users']),
            'support' => in_array($section, ['tickets']),
            default => false,
        };
    }
}
