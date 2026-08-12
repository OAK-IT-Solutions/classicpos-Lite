<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class PayPalService
{
    private string $clientId;
    private string $clientSecret;
    private string $apiUrl;
    private string $currency;

    public function __construct()
    {
        $this->clientId = config('paypal.client_id');
        $this->clientSecret = config('paypal.client_secret');
        $this->apiUrl = config('paypal.api_url');
        $this->currency = config('paypal.currency');
    }

    public function getAccessToken(): string
    {
        return Cache::remember('paypal_access_token', 28800, function () {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->post("{$this->apiUrl}/v1/oauth2/token", [
                    'grant_type' => 'client_credentials',
                ]);

            if ($response->failed()) {
                Log::error('PayPal token request failed', ['status' => $response->status(), 'body' => $response->body()]);
                throw new \RuntimeException('Failed to authenticate with PayPal');
            }

            return $response->json('access_token');
        });
    }

    public function createOrder(float $amount, string $description, string $returnUrl, string $cancelUrl): array
    {
        $token = $this->getAccessToken();

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'amount' => [
                    'currency_code' => $this->currency,
                    'value' => number_format($amount, 2, '.', ''),
                ],
                'description' => $description,
            ]],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'return_url' => $returnUrl,
                        'cancel_url' => $cancelUrl,
                        'user_action' => 'PAY_NOW',
                    ],
                ],
            ],
        ];

        $response = Http::withToken($token)
            ->withHeader('PayPal-Request-Id', 'ORDER-' . strtoupper(uniqid()))
            ->post("{$this->apiUrl}/v2/checkout/orders", $payload);

        if ($response->failed()) {
            Log::error('PayPal create order failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('Failed to create PayPal order: ' . ($response->json('message') ?? 'Unknown error'));
        }

        $data = $response->json();

        $approvalLink = collect($data['links'] ?? [])
            ->firstWhere('rel', 'payer-action');

        return [
            'order_id' => $data['id'],
            'status' => $data['status'],
            'approval_url' => $approvalLink['href'] ?? null,
        ];
    }

    public function captureOrder(string $orderId): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->withHeader('PayPal-Request-Id', 'CAPTURE-' . strtoupper(uniqid()))
            ->post("{$this->apiUrl}/v2/checkout/orders/{$orderId}/capture");

        if ($response->failed()) {
            Log::error('PayPal capture failed', ['order_id' => $orderId, 'status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('Failed to capture PayPal order: ' . ($response->json('message') ?? 'Unknown error'));
        }

        $data = $response->json();

        $capture = null;
        foreach ($data['purchase_units'] ?? [] as $unit) {
            foreach ($unit['payments']['captures'] ?? [] as $c) {
                $capture = $c;
                break 2;
            }
        }

        return [
            'order_id' => $data['id'],
            'status' => $data['status'],
            'capture_id' => $capture['id'] ?? null,
            'capture_status' => $capture['status'] ?? null,
            'amount' => $capture['amount']['value'] ?? null,
            'currency' => $capture['amount']['currency_code'] ?? $this->currency,
            'create_time' => $capture['create_time'] ?? null,
            'full_response' => $data,
        ];
    }

    public function getOrder(string $orderId): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->get("{$this->apiUrl}/v2/checkout/orders/{$orderId}");

        if ($response->failed()) {
            Log::error('PayPal get order failed', ['order_id' => $orderId, 'status' => $response->status()]);
            throw new \RuntimeException('Failed to get PayPal order');
        }

        return $response->json();
    }

    public function verifyWebhook(array $payload, array $headers): bool
    {
        $webhookId = config('paypal.webhook_id');
        if (!$webhookId) {
            Log::warning('PayPal webhook ID not configured, skipping verification');
            return false;
        }

        $token = $this->getAccessToken();

        $verificationPayload = [
            'auth_algo' => $headers['paypal-auth-algo'] ?? '',
            'cert_url' => $headers['paypal-cert-url'] ?? '',
            'transmission_id' => $headers['paypal-transmission-id'] ?? '',
            'transmission_sig' => $headers['paypal-transmission-sig'] ?? '',
            'transmission_time' => $headers['paypal-transmission-time'] ?? '',
            'webhook_id' => $webhookId,
            'webhook_event' => $payload,
        ];

        $response = Http::withToken($token)
            ->post("{$this->apiUrl}/v1/notifications/verify-webhook-signature", $verificationPayload);

        if ($response->failed()) {
            Log::error('PayPal webhook verification failed', ['status' => $response->status(), 'body' => $response->body()]);
            return false;
        }

        return ($response->json('verification_status') ?? '') === 'SUCCESS';
    }

    public function refund(string $captureId, float $amount, string $reason = ''): array
    {
        $token = $this->getAccessToken();

        $payload = [
            'amount' => [
                'currency_code' => $this->currency,
                'value' => number_format($amount, 2, '.', ''),
            ],
            'note_to_payer' => $reason ?: 'Refund',
        ];

        $response = Http::withToken($token)
            ->withHeader('PayPal-Request-Id', 'REFUND-' . strtoupper(uniqid()))
            ->post("{$this->apiUrl}/v2/payments/captures/{$captureId}/refund", $payload);

        if ($response->failed()) {
            Log::error('PayPal refund failed', ['capture_id' => $captureId, 'status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('Failed to refund PayPal payment');
        }

        $data = $response->json();
        return [
            'refund_id' => $data['id'] ?? null,
            'status' => $data['status'] ?? null,
            'amount' => $data['amount']['value'] ?? null,
        ];
    }
}
