<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\LocalQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Health', description: 'System health checks')]
class HealthController extends Controller
{
    #[OA\Get(
        path: '/api/v1/health',
        tags: ['Health'],
        summary: 'System health check',
        description: 'Returns database, Redis, and queue status with latency metrics.',
        responses: [
            new OA\Response(response: 200, description: 'System healthy', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'status', type: 'string', enum: ['healthy', 'degraded']),
                new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
                new OA\Property(property: 'checks', type: 'object'),
            ])),
            new OA\Response(response: 503, description: 'System degraded'),
        ]
    )]
    public function check(): JsonResponse
    {
        $checks = [];
        $healthy = true;

        // Database check
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $latency = round((microtime(true) - $start) * 1000, 2);
            $checks['database'] = ['status' => 'up', 'latency_ms' => $latency, 'driver' => DB::connection()->getDriverName()];
        } catch (\Throwable $e) {
            $checks['database'] = ['status' => 'down', 'error' => $e->getMessage()];
            $healthy = false;
        }

        // Redis check (skip in SQLite/offline mode)
        if (config('queue.default') === 'redis') {
            try {
                $start = microtime(true);
                \Illuminate\Support\Facades\Redis::ping();
                $latency = round((microtime(true) - $start) * 1000, 2);
                $checks['redis'] = ['status' => 'up', 'latency_ms' => $latency];
            } catch (\Throwable $e) {
                $checks['redis'] = ['status' => 'down', 'error' => $e->getMessage()];
                $healthy = false;
            }
        } else {
            $checks['redis'] = ['status' => 'skipped', 'reason' => 'offline_mode'];
        }

        // Queue check
        try {
            if (config('queue.default') === 'sync') {
                $checks['queue'] = ['status' => 'up', 'pending_jobs' => 0, 'driver' => 'sync'];
            } elseif (config('queue.default') === 'redis') {
                $pending = \Illuminate\Support\Facades\Redis::llen('queues:default');
                $checks['queue'] = ['status' => 'up', 'pending_jobs' => $pending, 'driver' => 'redis'];
            } else {
                $queueService = app(LocalQueueService::class);
                $lengths = $queueService->getLengths(['sync_queue', 'payment_queue']);
                $totalPending = array_sum($lengths);
                $checks['queue'] = ['status' => 'up', 'pending_jobs' => $totalPending, 'driver' => 'sqlite', 'queues' => $lengths];
            }
        } catch (\Throwable $e) {
            $checks['queue'] = ['status' => 'down', 'error' => $e->getMessage()];
            $healthy = false;
        }

        return response()->json([
            'status' => $healthy ? 'healthy' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'mode' => config('queue.default') === 'sync' ? 'offline' : 'online',
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }
}
