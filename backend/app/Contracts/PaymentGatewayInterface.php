<?php

namespace App\Contracts;

interface PaymentGatewayInterface
{
    public function getName(): string;

    public function charge(float $amount, array $metadata = []): array;

    public function refund(string $transactionId, float $amount, array $metadata = []): array;
}
