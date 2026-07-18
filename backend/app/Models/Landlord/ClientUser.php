<?php

namespace App\Models\Landlord;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class ClientUser extends Authenticatable
{
    use HasUuid, HasApiTokens, Notifiable;

    protected $connection = 'landlord';
    protected $table = 'client_users';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name', 'email', 'password', 'company_name', 'company_phone',
        'is_active', 'email_verified_at', 'last_login_at',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'client_user_id');
    }

    public function currentSubscription(): HasMany
    {
        return $this->hasMany(Subscription::class, 'client_user_id')
            ->whereIn('status', ['active', 'trialing']);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'client_user_id');
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class, 'client_user_id');
    }
}
