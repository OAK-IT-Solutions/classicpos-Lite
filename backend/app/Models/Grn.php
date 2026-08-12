<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use App\Traits\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grn extends Model
{
    use HasUuid, AuditLog;

    protected $table = 'grn';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'purchase_order_id', 'received_by', 'notes'
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function grnItems(): HasMany
    {
        return $this->hasMany(GrnItem::class);
    }

    public function getAuditBranchId(): ?string
    {
        return $this->purchaseOrder?->branch_id;
    }
}
