<?php

namespace App\Notifications;

use App\Models\Sale;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SaleCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Sale $sale,
        public readonly string $branchId,
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
            ->subject("Sale Completed — {$invoice}")
            ->line("A sale of **UGX {$total}** has been completed.")
            ->line("Invoice: **{$invoice}**")
            ->line("Payment method: **{$this->sale->payment_method}**")
            ->line("Date: **{$this->sale->created_at->format('d M Y, H:i')}**")
            ->action('View Sale', url("/sales/{$this->sale->id}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'sale_completed',
            'sale_id' => $this->sale->id,
            'invoice_number' => $this->sale->invoice_number,
            'total_amount' => $this->sale->total_amount,
            'payment_method' => $this->sale->payment_method,
            'branch_id' => $this->branchId,
            'message' => "Sale {$this->sale->invoice_number} completed — UGX " . number_format($this->sale->total_amount, 2),
        ];
    }
}
