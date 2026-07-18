<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EfrisFiscalLog extends Model
{
    use HasFactory, HasUuid;

    public $incrementing = false;
    protected $keyType = 'string';

    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    const STATUS_OFFLINE_QUEUED = 'offline_queued';

    protected $fillable = [
        'id',
        'branch_id',
        'sale_id',
        'efris_invoice_no',
        'efris_fdn',
        'efris_qr_code',
        'efris_verification_code',
        'request_payload',
        'response_payload',
        'status',
        'error_message',
        'retry_count',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'retry_count' => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function scopeForBranch($query, string $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeOfflineQueued($query)
    {
        return $query->where('status', self::STATUS_OFFLINE_QUEUED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING || $this->status === self::STATUS_OFFLINE_QUEUED;
    }

    public function markSuccess(array $response): void
    {
        $this->update([
            'status' => self::STATUS_SUCCESS,
            'response_payload' => $response,
            'efris_invoice_no' => $response['invoiceNo'] ?? $response['invoiceNo'] ?? null,
            'efris_fdn' => $response['fdn'] ?? $response['fiscalDocumentNo'] ?? null,
            'efris_qr_code' => $response['qrCode'] ?? null,
            'efris_verification_code' => $response['verificationCode'] ?? null,
            'error_message' => null,
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $error,
            'retry_count' => $this->retry_count + 1,
        ]);
    }
}
