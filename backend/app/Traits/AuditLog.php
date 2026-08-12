<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Request;

trait AuditLog
{
    protected static function bootAuditLog(): void
    {
        static::created(function (Model $model) {
            static::recordAudit($model, 'created', null, $model->getAttributes());
        });

        static::updated(function (Model $model) {
            $dirty = $model->getDirty();
            $original = $model->getOriginal();

            $oldValues = [];
            $newValues = [];
            foreach ($dirty as $key => $value) {
                $oldValues[$key] = $original[$key] ?? null;
                $newValues[$key] = $value;
            }

            if (!empty($newValues)) {
                static::recordAudit($model, 'updated', $oldValues, $newValues);
            }
        });

        static::deleted(function (Model $model) {
            static::recordAudit($model, 'deleted', $model->getAttributes(), null);
        });
    }

    protected static function recordAudit(
        Model $model,
        string $event,
        ?array $oldValues,
        ?array $newValues,
        ?string $description = null,
    ): void {
        DB::beginTransaction();
        try {
            $request = request();

            ActivityLog::create([
                'id' => (string) Str::uuid(),
                'user_id' => Auth::id(),
                'branch_id' => $model->getAttribute('branch_id')
                    ?? (method_exists($model, 'getAuditBranchId') ? $model->getAuditBranchId() : null),
                'auditable_type' => get_class($model),
                'auditable_id' => $model->getKey(),
                'event' => $event,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'url' => $request?->fullUrl(),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'description' => $description,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Failed to create audit log', [
                'model' => get_class($model),
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log a custom audit event (not tied to model lifecycle).
     */
    protected static function auditCustom(
        string $auditableType,
        string $auditableId,
        string $event,
        ?array $oldValues,
        ?array $newValues,
        ?string $description = null,
    ): void {
        DB::beginTransaction();
        try {
            $request = request();

            ActivityLog::create([
                'id' => (string) Str::uuid(),
                'user_id' => Auth::id(),
                'branch_id' => $request?->attributes->get('branch_id'),
                'auditable_type' => $auditableType,
                'auditable_id' => $auditableId,
                'event' => $event,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'url' => $request?->fullUrl(),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'description' => $description,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Failed to create custom audit log', [
                'type' => $auditableType,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
