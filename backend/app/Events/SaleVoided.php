<?php

namespace App\Events;

use App\Models\Sale;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SaleVoided
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Sale $sale,
        public readonly string $branchId,
    ) {}
}
