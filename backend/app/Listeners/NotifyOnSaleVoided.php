<?php

namespace App\Listeners;

use App\Events\SaleVoided;
use App\Notifications\SaleVoidedNotification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class NotifyOnSaleVoided implements ShouldQueue
{
    public function handle(SaleVoided $event): void
    {
        $admins = User::whereHas('roles', function ($q) {
            $q->where('name', 'admin');
        })->where('is_active', true)->get();

        Notification::send($admins, new SaleVoidedNotification($event->sale, $event->branchId));
    }
}
