<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HoldSale extends Model
{
    use HasUuid;

    protected $table = 'hold_sales';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'branch_id',
        'user_id',
        'customer_id',
        'cart_data',
        'promo_code',
        'tax_profile_id',
        'loyalty_points_redeemed',
        'note',
    ];

    protected $casts = [
        'cart_data' => 'array',
        'loyalty_points_redeemed' => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
