<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sync extends Model
{
    use HasUuid;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'branch_id', 'table_name', 'record_id', 'action', 'payload', 'status', 'conflict_data'
    ];

    protected $casts = [
        'payload' => 'array',
        'conflict_data' => 'array',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
