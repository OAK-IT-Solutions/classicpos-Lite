<?php

namespace App\Models\Landlord;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailVerification extends Model
{
    use HasUuid;

    protected $connection = 'landlord';
    protected $table = 'email_verifications';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'client_user_id', 'token', 'expires_at', 'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function clientUser(): BelongsTo
    {
        return $this->belongsTo(ClientUser::class, 'client_user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }
}
