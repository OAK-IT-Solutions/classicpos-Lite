<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PaymentService;

class ProcessPaymentsQueue extends Command
{
    protected $signature = 'sync:process-payments';
    protected $description = 'Process queued offline payments';

    public function handle(PaymentService $paymentService): int
    {
        $result = $paymentService->processOfflineQueue();
        $this->info("Processed {$result['processed']} payments, {$result['failed']} failed.");
        return Command::SUCCESS;
    }
}
