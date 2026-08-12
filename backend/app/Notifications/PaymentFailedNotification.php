<?php

namespace App\Notifications;

use App\Models\Sale;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Sale $sale,
        public readonly string $errorMessage,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $total = number_format($this->sale->total_amount, 2);
        $invoice = $this->sale->invoice_number ?? 'N/A';

        return (new MailMessage)
            ->subject("Payment Failed — {$invoice}")
            ->line("A payment of **UGX {$total}** for sale **{$invoice}** has failed.")
            ->line("Error: **{$this->errorMessage}**")
            ->line("The sale has been marked as payment failed and requires attention.")
            ->action('View Sale', url("/sales/{$this->sale->id}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_failed',
            'sale_id' => $this->sale->id,
            'invoice_number' => $this->sale->invoice_number,
            'total_amount' => $this->sale->total_amount,
            'error_message' => $this->errorMessage,
            'message' => "Payment failed for {$this->sale->invoice_number}: {$this->errorMessage}",
        ];
    }
}
