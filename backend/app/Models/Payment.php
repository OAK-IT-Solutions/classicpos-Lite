<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use App\Traits\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasUuid;
    use AuditLog;

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_VOIDED = 'voided';
    public const STATUS_REFUNDED = 'voided';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'sale_id',
        'amount',
        'method',
        'gateway',
        'txn_id',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
