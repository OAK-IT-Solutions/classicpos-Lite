<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification
{
    use Queueable;

    /**
     * @param array<int, array{product_id: string, product_name: string, branch_id: string, branch_name: string, current_stock: float, min_stock: float}> $items
     */
    public function __construct(
        public readonly array $items,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = count($this->items);
        $line = (new MailMessage)
            ->subject("Low Stock Alert — {$count} product(s)")
            ->line("**{$count} product(s)** are below minimum stock levels:");

        foreach (array_slice($this->items, 0, 10) as $item) {
            $line->line("- **{$item['product_name']}** ({$item['branch_name']}): {$item['current_stock']} remaining (min: {$item['min_stock']})");
        }

        if ($count > 10) {
            $line->line("...and " . ($count - 10) . " more.");
        }

        return $line->action('View Inventory', url('/inventory'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'low_stock',
            'items' => $this->items,
            'count' => count($this->items),
            'message' => count($this->items) . " product(s) below minimum stock",
        ];
    }
}
