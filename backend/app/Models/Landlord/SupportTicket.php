<?php

namespace App\Models\Landlord;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupportTicket extends Model
{
    use HasUuid, SoftDeletes;

    protected $connection = 'landlord';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id', 'user_id',
        'subject', 'description', 'ticket_number',
        'status', 'priority', 'category',
        'assigned_to', 'assigned_at',
        'first_response_at', 'resolved_at', 'closed_at',
        'message_count', 'unread_count',
        'metadata',
    ];

    protected $appends = ['sla_response_hours', 'sla_resolve_hours'];

    protected $casts = [
        'assigned_at' => 'datetime',
        'first_response_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'message_count' => 'integer',
        'unread_count' => 'integer',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class, 'ticket_id')->orderBy('created_at');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(TicketMessage::class, 'ticket_id')->latest('created_at');
    }

    // --- Status helpers ---

    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'waiting_reply']);
    }

    public function priorityLabel(): string
    {
        return match($this->priority) {
            'urgent' => 'Urgent',
            'high' => 'High',
            'medium' => 'Medium',
            default => 'Low',
        };
    }

    public function statusLabel(): string
    {
        return match($this->status) {
            'open' => 'Open',
            'in_progress' => 'In Progress',
            'waiting_reply' => 'Waiting for Reply',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
            default => $this->status,
        };
    }

    public function getSlaResponseHoursAttribute(): ?float
    {
        if (!$this->first_response_at) return null;
        return round($this->created_at->diffInHours($this->first_response_at), 1);
    }

    public function slaResponseHours(): ?float
    {
        return $this->sla_response_hours;
    }

    public function getSlaResolveHoursAttribute(): ?float
    {
        if (!$this->resolved_at) return null;
        return round($this->created_at->diffInHours($this->resolved_at), 1);
    }

    public function slaResolveHours(): ?float
    {
        return $this->sla_resolve_hours;
    }
}
