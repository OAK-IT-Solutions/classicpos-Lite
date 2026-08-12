<?php

namespace App\Listeners;

use App\Events\LowStockDetected;
use App\Notifications\LowStockNotification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class NotifyOnLowStock implements ShouldQueue
{
    public function handle(LowStockDetected $event): void
    {
        $admins = User::whereHas('roles', function ($q) {
            $q->where('name', 'admin');
        })->where('is_active', true)->get();

        Notification::send($admins, new LowStockNotification($event->items));
    }
}
