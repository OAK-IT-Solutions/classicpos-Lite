<?php

namespace App\Providers;

use App\Events\LowStockDetected;
use App\Events\PaymentFailed;
use App\Events\PaymentProcessed;
use App\Events\ReturnApproved;
use App\Events\SaleCompleted;
use App\Events\SaleVoided;
use App\Listeners\LogLowStockDetected;
use App\Listeners\LogPaymentFailed;
use App\Listeners\LogReturnApproved;
use App\Listeners\LogSaleCompleted;
use App\Listeners\LogSaleVoided;
use App\Listeners\NotifyOnLowStock;
use App\Listeners\NotifyOnPaymentFailed;
use App\Listeners\NotifyOnReturnApproved;
use App\Listeners\NotifyOnSaleCompleted;
use App\Listeners\NotifyOnSaleVoided;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        SaleCompleted::class => [
            NotifyOnSaleCompleted::class,
            LogSaleCompleted::class,
        ],
        SaleVoided::class => [
            NotifyOnSaleVoided::class,
            LogSaleVoided::class,
        ],
        PaymentProcessed::class => [
            //
        ],
        PaymentFailed::class => [
            NotifyOnPaymentFailed::class,
            LogPaymentFailed::class,
        ],
        LowStockDetected::class => [
            NotifyOnLowStock::class,
            LogLowStockDetected::class,
        ],
        ReturnApproved::class => [
            NotifyOnReturnApproved::class,
            LogReturnApproved::class,
        ],
    ];
}
