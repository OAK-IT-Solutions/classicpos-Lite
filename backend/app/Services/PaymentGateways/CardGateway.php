<?php

namespace App\Services\PaymentGateways;

use App\Contracts\PaymentGatewayInterface;

class CardGateway implements PaymentGatewayInterface
{
    public function getName(): string
    {
        return 'card';
    }

    public function charge(float $amount, array $metadata = []): array
    {
        $success = mt_rand(1, 10) > 3;

        return [
            'success' => $success,
            'transaction_id' => $success ? 'card_' . uniqid() : null,
            'amount' => $amount,
            'method' => 'card',
            'error' => $success ? null : 'Card declined',
        ];
    }

    public function refund(string $transactionId, float $amount, array $metadata = []): array
    {
        $success = mt_rand(1, 10) > 1;

        return [
            'success' => $success,
            'refund_transaction_id' => $success ? 'card_refund_' . uniqid() : null,
            'amount' => $amount,
            'error' => $success ? null : 'Refund rejected by gateway',
        ];
    }
}
