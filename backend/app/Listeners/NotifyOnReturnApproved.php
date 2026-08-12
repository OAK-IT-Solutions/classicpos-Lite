<?php

namespace App\Listeners;

use App\Events\ReturnApproved;
use App\Notifications\ReturnApprovedNotification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class NotifyOnReturnApproved implements ShouldQueue
{
    public function handle(ReturnApproved $event): void
    {
        $admins = User::whereHas('roles', function ($q) {
            $q->where('name', 'admin');
        })->where('is_active', true)->get();

        Notification::send($admins, new ReturnApprovedNotification($event->return));
    }
}
