<?php

namespace App\Notifications;

use App\Models\OrderReturn;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReturnApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly OrderReturn $return,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $total = number_format($this->return->total_amount ?? 0, 2);
        $reason = $this->return->reason ?? 'Not specified';

        return (new MailMessage)
            ->subject("Return Approved — #{$this->return->id}")
            ->line("A return of **UGX {$total}** has been approved.")
            ->line("Reason: **{$reason}**")
            ->line("Date: **{$this->return->created_at->format('d M Y, H:i')}**")
            ->action('View Return', url("/returns/{$this->return->id}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'return_approved',
            'return_id' => $this->return->id,
            'total_amount' => $this->return->total_amount,
            'reason' => $this->return->reason,
            'message' => "Return #{$this->return->id} approved — UGX " . number_format($this->return->total_amount ?? 0, 2),
        ];
    }
}
