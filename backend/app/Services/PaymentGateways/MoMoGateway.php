<?php

namespace App\Services\PaymentGateways;

use App\Contracts\PaymentGatewayInterface;

class MoMoGateway implements PaymentGatewayInterface
{
    public function getName(): string
    {
        return 'MTN MoMo';
    }

    public function charge(float $amount, array $metadata = []): array
    {
        $success = mt_rand(1, 10) > 2;

        return [
            'success' => $success,
            'transaction_id' => $success ? 'momo_' . uniqid() : null,
            'amount' => $amount,
            'method' => 'mobile_money',
            'error' => $success ? null : 'Insufficient funds',
        ];
    }

    public function refund(string $transactionId, float $amount, array $metadata = []): array
    {
        $success = mt_rand(1, 10) > 1;

        return [
            'success' => $success,
            'refund_transaction_id' => $success ? 'momo_refund_' . uniqid() : null,
            'amount' => $amount,
            'error' => $success ? null : 'Refund rejected by gateway',
        ];
    }
}
