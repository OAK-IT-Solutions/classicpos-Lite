<?php

namespace App\Listeners;

use App\Events\SaleVoided;
use App\Models\ActivityLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogSaleVoided implements ShouldQueue
{
    public function handle(SaleVoided $event): void
    {
        $sale = $event->sale;

        ActivityLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $sale->voided_by ?? $sale->user_id,
            'branch_id' => $sale->branch_id,
            'auditable_type' => \App\Models\Sale::class,
            'auditable_id' => $sale->id,
            'event' => 'voided',
            'old_values' => [
                'payment_status' => 'completed',
                'total' => $sale->total,
            ],
            'new_values' => [
                'payment_status' => $sale->payment_status,
                'void_reason' => $sale->void_reason ?? null,
            ],
            'description' => "Sale voided: {$sale->invoice_number} — reason: {$sale->void_reason}",
        ]);

        Log::info('Audit: sale voided', ['sale_id' => $sale->id]);
    }
}
