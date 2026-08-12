<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationItem extends Model
{
    use HasUuid;

    protected $table = 'reconciliation_items';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'reconciliation_id', 'journal_entry_id',
        'amount', 'type', 'is_cleared', 'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_cleared' => 'boolean',
    ];

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'reconciliation_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
