<?php

namespace App\Services;

use App\Models\Landlord\PaymentTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PesapalService
{
    private string $consumerKey;
    private string $consumerSecret;
    private string $apiUrl;
    private string $currency;

    public function __construct(
        private ?CommissionService $commissionService = null,
        private ?SubscriptionActivationService $subscriptionActivator = null,
    ) {
        $this->consumerKey = config('pesapal.consumer_key');
        $this->consumerSecret = config('pesapal.consumer_secret');
        $this->apiUrl = config('pesapal.api_url');
        $this->currency = config('pesapal.currency');
    }

    /**
     * Get OAuth2 token from Pesapal.
     */
    public function getToken(): string
    {
        $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
            ->post("{$this->apiUrl}/auth/v1/oauth/token", [
                'grant_type' => 'client_credentials',
            ]);

        if ($response->failed()) {
            Log::error('Pesapal token request failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('Failed to authenticate with Pesapal');
        }

        return $response->json('token');
    }

    /**
     * Submit an order to Pesapal and return iframe URL for checkout.
     */
    public function submitOrder(array $params): array
    {
        $token = $this->getToken();

        $payload = [
            'id' => $params['order_id'],
            'currency' => $params['currency'] ?? $this->currency,
            'amount' => $params['amount'],
            'description' => $params['description'] ?? 'ClassicPOS Subscription',
            'callback_url' => config('pesapal.callback_url'),
            'redirect_mode' => 'TOP',
            'notification_id' => config('pesapal.ipn_url'),
            'billing_address' => [
                'email_address' => $params['email'] ?? '',
                'phone_number' => $params['phone'] ?? '',
                'country_code' => $params['country'] ?? '',
                'first_name' => $params['first_name'] ?? '',
                'last_name' => $params['last_name'] ?? '',
                'line_1' => $params['address'] ?? '',
                'city' => $params['city'] ?? '',
                'state' => $params['state'] ?? '',
                'postal_code' => $params['postal_code'] ?? '',
            ],
        ];

        $response = Http::withToken($token)
            ->post("{$this->apiUrl}/orders/v3/submit", $payload);

        if ($response->failed()) {
            Log::error('Pesapal order submission failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('Failed to submit order to Pesapal');
        }

        $data = $response->json();

        return [
            'order_tracking_id' => $data['order_tracking_id'] ?? null,
            'merchant_reference' => $data['merchant_reference'] ?? null,
            'redirect_url' => $data['redirect_url'] ?? null,
        ];
    }

    /**
     * Get transaction status from Pesapal.
     */
    public function getTransactionStatus(string $orderTrackingId): array
    {
        $token = $this->getToken();

        $response = Http::withToken($token)
            ->get("{$this->apiUrl}/transactions/v3/get?orderTrackingId={$orderTrackingId}");

        if ($response->failed()) {
            Log::error('Pesapal status check failed', ['tracking_id' => $orderTrackingId, 'status' => $response->status()]);
            throw new \RuntimeException('Failed to get transaction status');
        }

        $data = $response->json();
        $status = $data['status'] ?? '0';

        return [
            'status' => config("pesapal.status_map.{$status}", 'pending'),
            'status_code' => $status,
            'amount' => $data['amount'] ?? 0,
            'description' => $data['description'] ?? '',
            'payment_method' => $data['payment_method'] ?? '',
            'payment_account' => $data['payment_account'] ?? '',
            'created_at' => $data['created_at'] ?? null,
        ];
    }

    /**
     * Handle Pesapal IPN (Instant Payment Notification).
     */
    public function handleIpn(string $orderTrackingId): ?PaymentTransaction
    {
        $statusData = $this->getTransactionStatus($orderTrackingId);

        $transaction = PaymentTransaction::where('gateway_ref', $orderTrackingId)->first();

        if (!$transaction) {
            Log::warning('Pesapal IPN for unknown transaction', ['tracking_id' => $orderTrackingId]);
            return null;
        }

        $pesapalStatus = $statusData['status'];
        $mappedStatus = match ($pesapalStatus) {
            'completed' => 'success',
            'failed', 'cancelled' => 'failed',
            default => 'pending',
        };

        if ($transaction->status !== 'success') {
            $transaction->update([
                'status' => $mappedStatus,
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'ipn_update' => now()->toIso8601String(),
                    'payment_method' => $statusData['payment_method'],
                    'payment_account' => $statusData['payment_account'],
                ]),
            ]);

            // If payment succeeded, activate the subscription
            if ($mappedStatus === 'success') {
                $activator = $this->subscriptionActivator ?? new SubscriptionActivationService($this->commissionService);
                $activator->activateFromTransaction($transaction);
            }
        }

        return $transaction;
    }

    /**
     * Refund a transaction.
     */
    public function refund(string $orderTrackingId, float $amount, string $reason = ''): bool
    {
        $token = $this->getToken();

        $response = Http::withToken($token)
            ->post("{$this->apiUrl}/transactions/v3/refund", [
                'order_tracking_id' => $orderTrackingId,
                'amount' => $amount,
                'reason' => $reason,
            ]);

        return $response->successful();
    }
}
