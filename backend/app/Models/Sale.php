<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasUuid;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PENDING_SYNC = 'pending_sync';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PAYMENT_FAILED = 'payment_failed';
    public const STATUS_VOIDED = 'voided';
    public const STATUS_REFUNDED = 'refunded';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'branch_id',
        'customer_id',
        'invoice_number',
        'total_amount',
        'tax_amount',
        'discount',
        'payment_method',
        'status',
        'sync_status',
        'efris_fdn',
        'efris_qr_code',
        'efris_verification_code',
        'efris_fiscal_status',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
