<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SQLite-backed queue service replacing Redis list operations.
 * Used for payment queue, sync queue, and other background job queues.
 */
class LocalQueueService
{
    /**
     * Push a job onto a queue.
     */
    public function push(string $queue, string $payload): void
    {
        DB::table('queue_jobs')->insert([
            'queue' => $queue,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }

    /**
     * Pop a job from the front of the queue (FIFO).
     * Returns null if queue is empty.
     */
    public function pop(string $queue): ?string
    {
        $job = DB::table('queue_jobs')
            ->where('queue', $queue)
            ->orderBy('id')
            ->first();

        if (!$job) {
            return null;
        }

        DB::table('queue_jobs')->where('id', $job->id)->delete();

        return $job->payload;
    }

    /**
     * Peek at the length of a queue without removing items.
     */
    public function length(string $queue): int
    {
        return (int) DB::table('queue_jobs')
            ->where('queue', $queue)
            ->count();
    }

    /**
     * Get all items from a queue without removing them.
     */
    public function peek(string $queue, int $limit = 100): array
    {
        return DB::table('queue_jobs')
            ->where('queue', $queue)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('payload')
            ->toArray();
    }

    /**
     * Remove a specific item from a queue by matching payload content.
     * Returns the number of rows removed.
     */
    public function remove(string $queue, string $payload, int $count = 1): int
    {
        return DB::table('queue_jobs')
            ->where('queue', $queue)
            ->where('payload', $payload)
            ->limit($count)
            ->delete();
    }

    /**
     * Atomically pop from one queue and push to another (move operation).
     * Simulates Redis's rpoplpush for crash-safe processing.
     */
    public function move(string $fromQueue, string $toQueue): ?string
    {
        return DB::transaction(function () use ($fromQueue, $toQueue) {
            $job = DB::table('queue_jobs')
                ->where('queue', $fromQueue)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (!$job) {
                return null;
            }

            // Move to processing queue
            DB::table('queue_jobs')->where('id', $job->id)->update([
                'queue' => $toQueue,
            ]);

            return $job->payload;
        });
    }

    /**
     * Get queue lengths for multiple queues at once.
     */
    public function getLengths(array $queues): array
    {
        $results = DB::table('queue_jobs')
            ->whereIn('queue', $queues)
            ->select('queue', DB::raw('COUNT(*) as count'))
            ->groupBy('queue')
            ->pluck('count', 'queue')
            ->toArray();

        return array_fill_keys($queues, 0) + $results;
    }

    /**
     * Clear all items from a queue.
     */
    public function clear(string $queue): int
    {
        return DB::table('queue_jobs')
            ->where('queue', $queue)
            ->delete();
    }
}
