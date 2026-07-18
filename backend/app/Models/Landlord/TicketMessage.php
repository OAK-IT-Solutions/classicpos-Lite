<?php

namespace App\Models\Landlord;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketMessage extends Model
{
    use HasUuid;

    protected $connection = 'landlord';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'ticket_id',
        'sender_type', 'sender_id', 'sender_name', 'sender_email',
        'message', 'attachments',
        'is_read', 'read_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function isFromAdmin(): bool
    {
        return $this->sender_type === 'admin';
    }

    public function isFromTenant(): bool
    {
        return $this->sender_type === 'tenant_user';
    }

    public function isSystem(): bool
    {
        return $this->sender_type === 'system';
    }
}
