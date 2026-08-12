<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    use HasUuid;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'branch_id', 'name', 'device_id', 'type', 'status',
        'description', 'firmware_version', 'ip_address', 'mac_address',
        'os', 'enrollment_token', 'enrolled_at', 'last_seen_at',
        'last_sync_at', 'capabilities', 'config',
        'certificate_serial', 'certificate_expires_at',
    ];

    protected $hidden = ['enrollment_token'];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'certificate_expires_at' => 'date',
        'capabilities' => 'json',
        'config' => 'json',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
