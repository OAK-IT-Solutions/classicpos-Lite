<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuditActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!$this->shouldLog($request, $response)) {
            return $response;
        }

        try {
            ActivityLog::create([
                'id' => (string) Str::uuid(),
                'user_id' => $request->user()?->id,
                'branch_id' => $request->user()?->branch_id ?? null,
                'auditable_type' => 'request',
                'auditable_id' => (string) Str::uuid(),
                'event' => 'request',
                'new_values' => [
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'status_code' => $response->getStatusCode(),
                ],
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'status_code' => $response->getStatusCode(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'description' => $request->method() . ' ' . $request->path(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('AuditActivity failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return $response;
    }

    private function shouldLog(Request $request, Response $response): bool
    {
        if (!env('AUDIT_LOG_REQUESTS', true)) {
            return false;
        }

        if (!$request->user()) {
            return false;
        }

        if (!$request->is('api/*')) {
            return false;
        }

        if ($request->method() === 'OPTIONS') {
            return false;
        }

        if ($request->is('api/v1/health')) {
            return false;
        }

        $methods = ['POST', 'PUT', 'PATCH', 'DELETE'];
        if (!in_array($request->method(), $methods)) {
            return false;
        }

        return true;
    }
}
