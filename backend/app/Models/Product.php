<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use App\Traits\AuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory, HasUuid;
    use AuditLog;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'barcode',
        'price',
        'cost',
        'description',
        'image',
        'category_id',
        'stock_uom',
        'min_stock',
        'is_active',
        'returnable',
    ];

    protected $casts = [
        'returnable' => 'boolean',
    ];

    public function getEffectiveReturnableAttribute(): bool
    {
        if ($this->returnable !== null) {
            return $this->returnable;
        }
        return $this->category?->returnable ?? false;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }
}
