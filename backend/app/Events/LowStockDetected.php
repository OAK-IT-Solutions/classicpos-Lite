<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LowStockDetected
{
    use Dispatchable, SerializesModels;

    /**
     * @param array<int, array{product_id: string, product_name: string, branch_id: string, branch_name: string, current_stock: float, min_stock: float}> $items
     */
    public function __construct(
        public readonly array $items,
    ) {}
}
