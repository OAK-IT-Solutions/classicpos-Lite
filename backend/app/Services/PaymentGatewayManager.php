<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Services\PaymentGateways\MoMoGateway;
use App\Services\PaymentGateways\AirtelMoneyGateway;
use App\Services\PaymentGateways\MpesaGateway;
use App\Services\PaymentGateways\CardGateway;
use App\Services\PaymentGateways\QrGateway;

class PaymentGatewayManager
{
    protected array $gateways = [];

    public function __construct()
    {
        $this->register(new MoMoGateway());
        $this->register(new AirtelMoneyGateway());
        $this->register(new MpesaGateway());
        $this->register(new CardGateway());
        $this->register(new QrGateway());
    }

    public function register(PaymentGatewayInterface $gateway): void
    {
        $this->gateways[$gateway->getName()] = $gateway;
    }

    public function get(string $name): ?PaymentGatewayInterface
    {
        return $this->gateways[$name] ?? null;
    }

    public function charge(string $gatewayName, float $amount, array $metadata = []): array
    {
        $gateway = $this->get($gatewayName);

        if (!$gateway) {
            return [
                'success' => false,
                'transaction_id' => null,
                'amount' => $amount,
                'method' => 'unknown',
                'error' => "Unknown gateway: {$gatewayName}",
            ];
        }

        return $gateway->charge($amount, $metadata);
    }

    public function refund(string $gatewayName, string $transactionId, float $amount, array $metadata = []): array
    {
        $gateway = $this->get($gatewayName);

        if (!$gateway) {
            return [
                'success' => false,
                'refund_transaction_id' => null,
                'amount' => $amount,
                'error' => "Unknown gateway: {$gatewayName}",
            ];
        }

        return $gateway->refund($transactionId, $amount, $metadata);
    }

    public function getAvailable(): array
    {
        return array_keys($this->gateways);
    }
}
