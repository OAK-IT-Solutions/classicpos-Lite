<?php

namespace App\Listeners;

use App\Events\LowStockDetected;
use App\Models\ActivityLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogLowStockDetected implements ShouldQueue
{
    public function handle(LowStockDetected $event): void
    {
        $product = $event->product;
        $stock = $event->stock ?? null;

        ActivityLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => null,
            'branch_id' => $stock?->branch_id ?? null,
            'auditable_type' => \App\Models\Product::class,
            'auditable_id' => $product->id,
            'event' => 'low_stock',
            'old_values' => [],
            'new_values' => [
                'product_id' => $product->id,
                'current_quantity' => $stock?->quantity ?? 0,
                'threshold' => $product->low_stock_threshold ?? 10,
            ],
            'description' => "Low stock: {$product->name} (qty: " . ($stock?->quantity ?? 0) . ")",
        ]);

        Log::warning('Audit: low stock detected', ['product_id' => $product->id]);
    }
}
