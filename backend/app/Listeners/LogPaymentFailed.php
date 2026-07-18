<?php

namespace App\Listeners;

use App\Events\PaymentFailed;
use App\Models\ActivityLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogPaymentFailed implements ShouldQueue
{
    public function handle(PaymentFailed $event): void
    {
        $payment = $event->payment;

        ActivityLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $payment->user_id ?? null,
            'branch_id' => $payment->branch_id ?? null,
            'auditable_type' => \App\Models\Payment::class,
            'auditable_id' => $payment->id,
            'event' => 'failed',
            'old_values' => ['status' => $payment->getOriginal('status') ?? 'pending'],
            'new_values' => ['status' => 'failed', 'amount' => $payment->amount],
            'description' => "Payment failed: #{$payment->id} — {$payment->amount}",
        ]);

        Log::info('Audit: payment failed', ['payment_id' => $payment->id]);
    }
}
