<?php

namespace App\Listeners;

use App\Events\SaleCompleted;
use App\Models\ActivityLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogSaleCompleted implements ShouldQueue
{
    public function handle(SaleCompleted $event): void
    {
        $sale = $event->sale;

        ActivityLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $sale->user_id,
            'branch_id' => $sale->branch_id,
            'auditable_type' => \App\Models\Sale::class,
            'auditable_id' => $sale->id,
            'event' => 'created',
            'old_values' => [],
            'new_values' => [
                'invoice_number' => $sale->invoice_number,
                'total' => $sale->total,
                'payment_status' => $sale->payment_status,
                'payment_method' => $sale->payment_method,
            ],
            'description' => "Sale completed: {$sale->invoice_number} — {$sale->total}",
            'ip_address' => $sale->ip_address ?? null,
            'user_agent' => $sale->user_agent ?? null,
        ]);

        Log::info('Audit: sale created', ['sale_id' => $sale->id]);
    }
}
