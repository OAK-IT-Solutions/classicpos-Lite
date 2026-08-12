<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockIncreased
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $productId,
        public readonly string $branchId,
        public readonly float $quantity,
        public readonly string $referenceType,
    ) {}
}
