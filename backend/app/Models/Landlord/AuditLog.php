<?php

namespace App\Models\Landlord;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasUuid;

    protected $connection = 'landlord';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'user_type', 'user_name', 'user_email',
        'action', 'action_group',
        'subject_type', 'subject_id', 'subject_description',
        'old_values', 'new_values', 'metadata',
        'ip_address', 'user_agent', 'url', 'method',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    // --- Static helpers ---

    public static function log(
        string $action,
        string $actionGroup = 'general',
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?string $subjectDescription = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
    ): static {
        $user = auth()->user();

        return static::create([
            'user_id' => $user?->id,
            'user_type' => static::resolveUserType($user),
            'user_name' => $user?->name,
            'user_email' => $user?->email,
            'action' => $action,
            'action_group' => $actionGroup,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'subject_description' => $subjectDescription,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->url(),
            'method' => request()->method(),
            'created_at' => now(),
        ]);
    }

    private static function resolveUserType(?object $user): string
    {
        if (!$user) return 'system';
        if (str_contains($user::class, 'Landlord')) return 'admin';
        if (str_contains($user::class, 'Agent')) return 'agent';
        return 'tenant_user';
    }
}
