<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use App\Traits\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasUuid, AuditLog;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'branch_id', 'payee', 'amount', 'method', 'category',
        'reference', 'expense_date', 'notes', 'purchase_order_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    public static array $categories = [
        'Inventory Purchase', 'Rent', 'Utilities', 'Wages & Salaries',
        'Maintenance', 'Transport', 'Marketing', 'Insurance',
        'Licenses & Permits', 'Office Supplies', 'Professional Fees',
        'Taxes', 'Other',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
