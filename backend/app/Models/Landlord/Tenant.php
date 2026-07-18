<?php

namespace App\Models\Landlord;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    use HasUuid, SoftDeletes;

    protected $connection = 'landlord';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'name', 'slug', 'domain',
        'db_host', 'db_port', 'db_name', 'db_username', 'db_password',
        'status', 'trial_ends_at', 'suspended_at', 'cancelled_at',
        'business_name', 'business_email', 'business_phone',
        'created_by_agent_id', 'referred_by_agent_id', 'metadata',
    ];

    protected $hidden = ['db_password'];

    protected $casts = [
        'db_port' => 'integer',
        'trial_ends_at' => 'datetime',
        'suspended_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    // --- Relationships ---

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latest();
    }

    public function currentSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->ofMany([], fn ($q) => $q->whereIn('status', ['active', 'trialing'])->latest());
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(AgentReferral::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'subject_id')
            ->where('subject_type', 'Tenant');
    }

    // --- Helpers ---

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isTrial(): bool
    {
        return $this->status === 'trial';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function getDatabaseConfig(): array
    {
        return [
            'driver' => 'pgsql',
            'host' => $this->db_host,
            'port' => $this->db_port,
            'database' => $this->db_name,
            'username' => $this->db_username,
            'password' => $this->db_password,
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ];
    }
}
