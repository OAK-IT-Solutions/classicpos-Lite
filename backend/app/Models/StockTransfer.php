<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use App\Traits\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransfer extends Model
{
    use HasUuid, AuditLog;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'from_warehouse_id', 'to_warehouse_id', 'status', 'notes', 'transferred_at',
    ];

    protected $casts = [
        'transferred_at' => 'datetime',
    ];

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function getAuditBranchId(): ?string
    {
        return $this->fromWarehouse?->branch_id ?? $this->toWarehouse?->branch_id;
    }
}
