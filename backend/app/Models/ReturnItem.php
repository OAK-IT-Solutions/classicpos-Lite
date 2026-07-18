<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnItem extends Model
{
    use HasUuid;

    protected $table = 'return_items';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'return_id', 'product_id', 'quantity', 'reason', 'condition',
    ];

    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class, 'return_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
