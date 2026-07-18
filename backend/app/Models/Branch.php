<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use App\Traits\AuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Branch extends Model
{
    use HasFactory, HasUuid, AuditLog;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'name', 'location', 'timezone', 'edge_device_id', 'cloud_sync_status', 'business_type'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'business_type' => 'string',
    ];

    public function inventory(): HasManyThrough
    {
        return $this->hasManyThrough(Inventory::class, Warehouse::class, 'branch_id', 'warehouse_id', 'id', 'id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function suppliers(): HasManyThrough
    {
        return $this->hasManyThrough(Supplier::class, PurchaseOrder::class, 'branch_id', 'id', 'id', 'supplier_id');
    }

    public function syncs(): HasMany
    {
        return $this->hasMany(Sync::class);
    }

    public function businessProfile(): HasOne
    {
        return $this->hasOne(BusinessProfile::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }
}
