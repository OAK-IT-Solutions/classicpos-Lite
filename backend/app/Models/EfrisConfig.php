<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EfrisConfig extends Model
{
    use HasFactory, HasUuid;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'integration_id',
        'branch_id',
        'tin',
        'weaf_email',
        'weaf_token',
        'weaf_token_expires_at',
        'weaf_environment',
        'company_name',
        'company_weaf_id',
        'auto_fiscalize',
        'fiscalize_receipts',
    ];

    protected $casts = [
        'weaf_token' => 'encrypted',
        'weaf_token_expires_at' => 'datetime',
        'auto_fiscalize' => 'boolean',
        'fiscalize_receipts' => 'boolean',
    ];

    protected $hidden = [
        'weaf_token',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function fiscalLogs()
    {
        return $this->hasMany(EfrisFiscalLog::class, 'branch_id', 'branch_id')
            ->where('branch_id', $this->branch_id);
    }

    public function isTokenExpired(): bool
    {
        return !$this->weaf_token_expires_at || $this->weaf_token_expires_at->isPast();
    }

    public function isProduction(): bool
    {
        return $this->weaf_environment === 'production';
    }
}
