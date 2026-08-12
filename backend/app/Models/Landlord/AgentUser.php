<?php

namespace App\Models\Landlord;

use App\Models\User;
use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class AgentUser extends User
{
    use HasUuid, HasApiTokens, Notifiable;

    protected $connection = 'landlord';
    protected $table = 'agent_users';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name', 'email', 'password', 'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function agent(): HasOne
    {
        return $this->hasOne(Agent::class, 'user_id');
    }
}
