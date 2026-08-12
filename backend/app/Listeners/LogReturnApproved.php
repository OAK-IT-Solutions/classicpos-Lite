<?php

namespace App\Listeners;

use App\Events\ReturnApproved;
use App\Models\ActivityLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogReturnApproved implements ShouldQueue
{
    public function handle(ReturnApproved $event): void
    {
        $return = $event->return;

        ActivityLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $return->approved_by ?? null,
            'branch_id' => $return->branch_id ?? null,
            'auditable_type' => \App\Models\OrderReturn::class,
            'auditable_id' => $return->id,
            'event' => 'approved',
            'old_values' => ['status' => $return->getOriginal('status') ?? 'pending'],
            'new_values' => ['status' => 'approved'],
            'description' => "Return approved: #{$return->id}",
        ]);

        Log::info('Audit: return approved', ['return_id' => $return->id]);
    }
}
