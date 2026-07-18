<?php

namespace App\Notifications;

use App\Models\Landlord\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketCreated extends Notification implements ShouldQueue
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
            ->subject('New Support Ticket: ' . $this->ticket->ticket_number)
            ->line('A new support ticket has been created.')
            ->line('**Ticket:** ' . $this->ticket->ticket_number)
            ->line('**Subject:** ' . $this->ticket->subject)
            ->line('**Priority:** ' . ucfirst($this->ticket->priority))
            ->line('**Category:** ' . ucfirst(str_replace('_', ' ', $this->ticket->category)))
            ->action('View Ticket', url('/admin/tickets/' . $this->ticket->id))
            ->line('Please respond as soon as possible.');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'ticket.created',
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'subject' => $this->ticket->subject,
            'priority' => $this->ticket->priority,
            'category' => $this->ticket->category,
            'message' => "New ticket {$this->ticket->ticket_number}: {$this->ticket->subject}",
        ];
    }
}
