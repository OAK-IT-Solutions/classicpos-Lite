<?php

namespace App\Listeners;

use App\Events\SaleCompleted;
use App\Notifications\SaleCompletedNotification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class NotifyOnSaleCompleted implements ShouldQueue
{
    public function handle(SaleCompleted $event): void
    {
        $sale = $event->sale;

        $admins = User::whereHas('roles', function ($q) {
            $q->where('name', 'admin');
        })->where('is_active', true)->get();

        Notification::send($admins, new SaleCompletedNotification($sale, $event->branchId));
    }
}
