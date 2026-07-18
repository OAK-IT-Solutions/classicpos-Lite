<?php

namespace App\Listeners;

use App\Events\PaymentFailed;
use App\Notifications\PaymentFailedNotification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class NotifyOnPaymentFailed implements ShouldQueue
{
    public function handle(PaymentFailed $event): void
    {
        $admins = User::whereHas('roles', function ($q) {
            $q->where('name', 'admin');
        })->where('is_active', true)->get();

        Notification::send($admins, new PaymentFailedNotification($event->sale, $event->errorMessage));
    }
}
