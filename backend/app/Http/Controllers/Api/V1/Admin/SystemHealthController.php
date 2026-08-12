<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Landlord\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Artisan;
use OpenApi\Attributes as OA;

class SystemHealthController extends Controller
{
    #[OA\Get(path: "/admin/health", tags: ["Admin Health"], summary: "System health status", responses: [new OA\Response(response: 200, description: "Health status returned")])]
    public function status(): JsonResponse
    {
        // Database connections
        $landlordDb = $this->checkDatabase('landlord');
        $tenantDbCount = Tenant::where('status', '!=', 'cancelled')->count();

        // Disk usage
        $disk = disk_free_space('/') ? [
            'total' => round(disk_total_space('/') / 1073741824, 2),
            'free' => round(disk_free_space('/') / 1073741824, 2),
            'used' => round((disk_total_space('/') - disk_free_space('/')) / 1073741824, 2),
            'percent' => round((1 - disk_free_space('/') / disk_total_space('/')) * 100, 1),
        ] : null;

        // Memory
        $memory = [
            'usage_mb' => round(memory_get_usage(true) / 1048576, 2),
            'peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
        ];

        // PHP info
        $php = [
            'version' => PHP_VERSION,
            'extensions' => $this->getImportantExtensions(),
        ];

        // Laravel version
        $laravel = [
            'version' => app()->version(),
            'environment' => app()->environment(),
        ];

        // Queue status
        $queueJobs = 0;
        $queueDriver = config('queue.default');
        if ($queueDriver === 'redis') {
            try {
                $queueJobs = \Illuminate\Support\Facades\Redis::connection()->llen('queues:default');
            } catch (\Exception $e) {
                // Redis not available
            }
        }

        return response()->json([
            'status' => 'healthy',
            'landlord_database' => $landlordDb,
            'tenant_count' => $tenantDbCount,
            'disk' => $disk,
            'memory' => $memory,
            'php' => $php,
            'laravel' => $laravel,
            'queue' => [
                'pending_jobs' => $queueJobs,
                'driver' => $queueDriver,
            ],
            'redis' => $this->checkRedis(),
            'mode' => config('landlord.self_hosted', true) ? 'Self-Hosted' : 'SaaS',
            'database_driver' => config('database.default'),
            'uptime' => $this->getUptime(),
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    private function checkDatabase(string $connection): array
    {
        try {
            $start = microtime(true);
            DB::connection($connection)->getPdo();
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'connected',
                'latency_ms' => $latency,
                'database' => config("database.connections.{$connection}.database"),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkRedis(): array
    {
        if (config('queue.default') !== 'redis') {
            return ['status' => 'skipped', 'reason' => 'offline_mode'];
        }

        try {
            $start = microtime(true);
            Redis::connection()->ping();
            $latency = round((microtime(true) - $start) * 1000, 2);
            return ['status' => 'available', 'latency_ms' => $latency];
        } catch (\Exception $e) {
            return ['status' => 'unavailable', 'error' => $e->getMessage()];
        }
    }

    private function getUptime(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return function_exists('exec') ? @exec('wmic os get lastbootuptime') ?? 'N/A' : 'N/A';
        }
        return function_exists('exec') ? @exec('uptime -p') ?? 'N/A' : 'N/A';
    }

    private function getImportantExtensions(): array
    {
        $important = ['pdo', 'pdo_pgsql', 'pdo_sqlite', 'sqlite3', 'redis', 'mbstring', 'openssl', 'json', 'curl', 'xml', 'bcmath', 'intl', 'zip'];
        return array_filter($important, fn ($ext) => extension_loaded($ext));
    }
}
