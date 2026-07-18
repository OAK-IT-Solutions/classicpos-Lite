<?php

namespace App\Notifications;

use App\Models\Landlord\SupportTicket;
use App\Models\Landlord\TicketMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketReply extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public SupportTicket $ticket,
        public TicketMessage $message,
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reply on Ticket: ' . $this->ticket->ticket_number)
            ->line('A new reply has been posted on support ticket ' . $this->ticket->ticket_number . '.')
            ->line('**From:** ' . $this->message->sender_name)
            ->line('**Message:** ' . mb_substr($this->message->message, 0, 200) . (strlen($this->message->message) > 200 ? '...' : ''))
            ->action('View Ticket', url('/admin/tickets/' . $this->ticket->id));
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'ticket.reply',
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'subject' => $this->ticket->subject,
            'sender_name' => $this->message->sender_name,
            'message' => "Reply on {$this->ticket->ticket_number} from {$this->message->sender_name}",
        ];
    }
}
