<?php

namespace App\Services\PaymentGateways;

use App\Contracts\PaymentGatewayInterface;

class QrGateway implements PaymentGatewayInterface
{
    public function getName(): string
    {
        return 'qr';
    }

    public function charge(float $amount, array $metadata = []): array
    {
        $success = mt_rand(1, 10) > 2;

        return [
            'success' => $success,
            'transaction_id' => $success ? 'qr_' . uniqid() : null,
            'amount' => $amount,
            'method' => 'qr',
            'error' => $success ? null : 'QR scan failed',
        ];
    }

    public function refund(string $transactionId, float $amount, array $metadata = []): array
    {
        $success = mt_rand(1, 10) > 1;

        return [
            'success' => $success,
            'refund_transaction_id' => $success ? 'qr_refund_' . uniqid() : null,
            'amount' => $amount,
            'error' => $success ? null : 'Refund rejected by gateway',
        ];
    }
}
