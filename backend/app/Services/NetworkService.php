<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class NetworkService
{
    private ?LocalQueueService $queue = null;

    public function __construct()
    {
        if (config('queue.default') !== 'redis') {
            $this->queue = app(LocalQueueService::class);
        }
    }

    public function checkConnectivity()
    {
        // Test HTTP connectivity to cloud API
        $httpCheck = $this->checkHttpConnectivity();

        // Test Redis connectivity (only if Redis is configured)
        $redisAvailable = false;
        if (config('queue.default') === 'redis') {
            try {
                $ping = \Illuminate\Support\Facades\Redis::ping();
                $redisAvailable = $ping === true || $ping === 'PONG' || $ping === '+PONG';
            } catch (\Exception $e) {
                $redisAvailable = false;
            }
        } else {
            // In offline mode, check if we can reach the internet at all
            $redisAvailable = $httpCheck;
        }

        return [
            'redis' => $redisAvailable,
            'http' => $httpCheck,
            'overall' => $redisAvailable && $httpCheck,
            'latency_ms' => $this->measureLatency(),
            'bandwidth_mbps' => $this->measureBandwidth(),
        ];
    }

    private function checkHttpConnectivity()
    {
        try {
            $response = Http::timeout(5)->get(config('app.cloud_api_url', 'https://api.classicpos.app'));
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function measureLatency()
    {
        $start = microtime(true);
        if (config('queue.default') === 'redis') {
            try {
                \Illuminate\Support\Facades\Redis::ping();
            } catch (\Exception $e) {
                // Redis not available
            }
        }
        return round((microtime(true) - $start) * 1000, 2);
    }

    private function measureBandwidth()
    {
        // In offline mode, bandwidth is not applicable
        if (config('queue.default') !== 'redis') {
            return 0.0;
        }

        try {
            $testData = str_repeat('x', 1024 * 1024); // 1MB
            $start = microtime(true);
            \Illuminate\Support\Facades\Redis::set('bandwidth_test', $testData, 60);
            $elapsed = microtime(true) - $start;
            return round((1 / $elapsed) * 8, 2); // Mbps
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    public function getSyncStatus()
    {
        $connectivity = $this->checkConnectivity();

        if ($this->queue) {
            $queueLengths = $this->queue->getLengths(['sync_queue', 'payment_queue']);
            $queueLength = $queueLengths['sync_queue'] ?? 0;
            $paymentQueueLength = $queueLengths['payment_queue'] ?? 0;
        } else {
            $queueLength = \Illuminate\Support\Facades\Redis::llen('sync_queue');
            $paymentQueueLength = \Illuminate\Support\Facades\Redis::llen('payment_queue');
        }

        return [
            'online' => $connectivity['overall'],
            'latency_ms' => $connectivity['latency_ms'],
            'bandwidth_mbps' => $connectivity['bandwidth_mbps'],
            'sync_queue' => $queueLength,
            'payment_queue' => $paymentQueueLength,
            'last_sync' => $this->queue ? $this->getLocalValue('last_sync_at') : \Illuminate\Support\Facades\Redis::get('last_sync_at'),
            'next_sync' => $this->queue ? $this->getLocalValue('next_sync_at') : \Illuminate\Support\Facades\Redis::get('next_sync_at'),
        ];
    }

    private function getLocalValue(string $key): ?string
    {
        return \Illuminate\Support\Facades\Cache::get("network:{$key}");
    }

    private function setLocalValue(string $key, string $value): void
    {
        \Illuminate\Support\Facades\Cache::put("network:{$key}", $value, 3600);
    }
}
