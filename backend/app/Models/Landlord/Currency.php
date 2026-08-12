<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $connection = 'landlord';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'code';

    protected $fillable = [
        'code', 'name', 'symbol', 'exchange_rate_to_usd',
        'is_default', 'is_active', 'decimal_places',
    ];

    protected $casts = [
        'exchange_rate_to_usd' => 'decimal:6',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'decimal_places' => 'integer',
    ];
}
