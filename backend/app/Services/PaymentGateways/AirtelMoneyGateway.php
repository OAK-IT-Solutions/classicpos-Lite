<?php

namespace App\Services\PaymentGateways;

use App\Contracts\PaymentGatewayInterface;

class AirtelMoneyGateway implements PaymentGatewayInterface
{
    public function getName(): string
    {
        return 'Airtel Money';
    }

    public function charge(float $amount, array $metadata = []): array
    {
        $success = mt_rand(1, 10) > 2;

        return [
            'success' => $success,
            'transaction_id' => $success ? 'airtel_' . uniqid() : null,
            'amount' => $amount,
            'method' => 'mobile_money',
            'error' => $success ? null : 'Network error',
        ];
    }

    public function refund(string $transactionId, float $amount, array $metadata = []): array
    {
        $success = mt_rand(1, 10) > 1;

        return [
            'success' => $success,
            'refund_transaction_id' => $success ? 'airtel_refund_' . uniqid() : null,
            'amount' => $amount,
            'error' => $success ? null : 'Refund rejected by gateway',
        ];
    }
}
