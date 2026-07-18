<?php

namespace App\Services\PaymentGateways;

use App\Contracts\PaymentGatewayInterface;

class MpesaGateway implements PaymentGatewayInterface
{
    public function getName(): string
    {
        return 'M-Pesa';
    }

    public function charge(float $amount, array $metadata = []): array
    {
        $success = mt_rand(1, 10) > 2;

        return [
            'success' => $success,
            'transaction_id' => $success ? 'mpesa_' . uniqid() : null,
            'amount' => $amount,
            'method' => 'mobile_money',
            'error' => $success ? null : 'Transaction declined',
        ];
    }

    public function refund(string $transactionId, float $amount, array $metadata = []): array
    {
        $success = mt_rand(1, 10) > 1;

        return [
            'success' => $success,
            'refund_transaction_id' => $success ? 'mpesa_refund_' . uniqid() : null,
            'amount' => $amount,
            'error' => $success ? null : 'Refund rejected by gateway',
        ];
    }
}
