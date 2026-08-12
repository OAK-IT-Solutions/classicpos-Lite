<?php

namespace App\Notifications;

use App\Models\Landlord\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketResolved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SupportTicket $ticket) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Ticket Resolved: ' . $this->ticket->ticket_number)
            ->line('Your support ticket has been resolved.')
            ->line('**Ticket:** ' . $this->ticket->ticket_number)
            ->line('**Subject:** ' . $this->ticket->subject)
            ->line('If you need further assistance, please reply to reopen the ticket.');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'ticket.resolved',
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'subject' => $this->ticket->subject,
            'message' => "Ticket {$this->ticket->ticket_number} has been resolved",
        ];
    }
}
