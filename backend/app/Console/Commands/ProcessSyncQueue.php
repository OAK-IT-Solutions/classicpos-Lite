<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use App\Models\Sale;
use App\Models\Sync;

class ProcessSyncQueue extends Command
{
    protected $signature = 'sync:process-queue';
    protected $description = 'Process pending sync queue items';

    public function handle(): int
    {
        $processed = 0;
        while ($item = Redis::rpop('sync_queue')) {
            $data = json_decode($item, true);

            if (!$data) {
                continue;
            }

            $event = $data['event'] ?? 'UNKNOWN';
            $eventData = $data['data'] ?? [];
            $saleId = $eventData['id'] ?? null;

            if ($event === 'SALE_CREATED' && $saleId) {
                Sale::where('id', $saleId)->update(['status' => Sale::STATUS_COMPLETED]);
            }

            Sync::create([
                'branch_id' => $eventData['branch_id'] ?? null,
                'table_name' => match ($event) {
                    'SALE_CREATED' => 'sales',
                    default => 'unknown',
                },
                'record_id' => $saleId,
                'action' => 'create',
                'status' => 'synced',
            ]);

            $processed++;
        }

        $this->info("Processed {$processed} sync items.");
        return Command::SUCCESS;
    }
}
