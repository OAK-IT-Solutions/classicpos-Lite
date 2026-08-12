<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;
use Carbon\Carbon;

class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune';
    protected $description = 'Delete audit logs older than retention period';

    public function handle(): int
    {
        $months = config('audit.retention_months', 24);
        $cutoff = Carbon::now()->subMonths($months);

        $deleted = ActivityLog::where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} audit logs older than {$months} months (before {$cutoff->format('Y-m-d')})");

        return Command::SUCCESS;
    }
}
