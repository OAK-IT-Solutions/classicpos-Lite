<?php

namespace App\Events;

use App\Models\OrderReturn;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReturnApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly OrderReturn $return,
    ) {}
}
