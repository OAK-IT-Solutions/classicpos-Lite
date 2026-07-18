<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use App\Traits\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAdjustment extends Model
{
    use HasUuid, AuditLog;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'branch_id', 'product_id', 'warehouse_id',
        'quantity', 'type', 'reason', 'reference',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
