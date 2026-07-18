<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasUuid;

    protected $table = 'purchase_order_items';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'purchase_order_id', 'product_id', 'quantity', 'unit_cost',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
