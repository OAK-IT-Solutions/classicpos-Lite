<?php

namespace App\Services;

use App\Models\EfrisConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EfrisService
{
    private string $baseUrl = 'https://weafcompany.com';

    public function __construct()
    {
        $this->baseUrl = config('services.weaf.base_url', 'https://weafcompany.com');
    }

    private function client(?string $token = null): \Illuminate\Http\Client\PendingRequest
    {
        $client = Http::timeout(30)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);

        if ($token) {
            $client = $client->withHeaders(['Authorization' => "Bearer {$token}"]);
        }

        return $client;
    }

    private function environmentHeader(string $environment): array
    {
        return ['X-Environment' => ucfirst($environment)];
    }

    public function generateToken(string $email, string $password, int $expiryDays = 30, string $tokenName = 'ClassicPOS'): array
    {
        $response = $this->client()->post("{$this->baseUrl}/api/v1/auth/generate-token", [
            'username' => $email,
            'password' => $password,
            'expiry_days' => $expiryDays,
            'token_name' => $tokenName,
        ]);

        return $this->handleResponse($response, 'generate_token');
    }

    public function refreshToken(string $token, int $expiryDays = 60, string $tokenName = 'ClassicPOS Refresh'): array
    {
        $response = $this->client($token)->post("{$this->baseUrl}/api/v1/auth/refresh-token", [
            'expiry_days' => $expiryDays,
            'token_name' => $tokenName,
        ]);

        return $this->handleResponse($response, 'refresh_token');
    }

    public function validateToken(string $token): array
    {
        $response = $this->client($token)->post("{$this->baseUrl}/api/v1/auth/validate-token", [
            'token' => $token,
        ]);

        return $this->handleResponse($response, 'validate_token');
    }

    public function getRegistrationDetails(string $tin, string $token, string $environment = 'sandbox'): array
    {
        $response = $this->client($token)
            ->withHeaders($this->environmentHeader($environment))
            ->get("{$this->baseUrl}/api/{$tin}/registration-details");

        return $this->handleResponse($response, 'registration_details');
    }

    public function generateFiscalInvoice(string $tin, string $token, array $data, string $environment = 'sandbox'): array
    {
        $response = $this->client($token)
            ->withHeaders($this->environmentHeader($environment))
            ->post("{$this->baseUrl}/api/{$tin}/generate-fiscal-invoice", $data);

        return $this->handleResponse($response, 'generate_fiscal_invoice');
    }

    public function generateFiscalReceipt(string $tin, string $token, array $data, string $environment = 'sandbox'): array
    {
        $response = $this->client($token)
            ->withHeaders($this->environmentHeader($environment))
            ->post("{$this->baseUrl}/api/{$tin}/generate-fiscal-receipt", $data);

        return $this->handleResponse($response, 'generate_fiscal_receipt');
    }

    public function queryInvoices(string $tin, string $token, array $filters, string $environment = 'sandbox'): array
    {
        $response = $this->client($token)
            ->withHeaders($this->environmentHeader($environment))
            ->post("{$this->baseUrl}/api/{$tin}/invoice-receipt-query", $filters);

        return $this->handleResponse($response, 'query_invoices');
    }

    public function getInvoiceDetails(string $tin, string $token, string $invoiceNo, string $environment = 'sandbox'): array
    {
        $response = $this->client($token)
            ->withHeaders($this->environmentHeader($environment))
            ->get("{$this->baseUrl}/api/{$tin}/invoice-details/{$invoiceNo}");

        return $this->handleResponse($response, 'invoice_details');
    }

    public function applyCreditNote(string $tin, string $token, array $data, string $environment = 'sandbox'): array
    {
        $response = $this->client($token)
            ->withHeaders($this->environmentHeader($environment))
            ->post("{$this->baseUrl}/api/{$tin}/apply-for-creditnote", $data);

        return $this->handleResponse($response, 'apply_credit_note');
    }

    public function syncProducts(string $tin, string $token, array $filters, string $environment = 'sandbox'): array
    {
        $response = $this->client($token)
            ->withHeaders($this->environmentHeader($environment))
            ->post("{$this->baseUrl}/api/{$tin}/sync-products", $filters);

        return $this->handleResponse($response, 'sync_products');
    }

    public function registerProducts(string $tin, string $token, array $products, string $environment = 'sandbox'): array
    {
        $response = $this->client($token)
            ->withHeaders($this->environmentHeader($environment))
            ->post("{$this->baseUrl}/api/{$tin}/register-product", [
                'products' => $products,
            ]);

        return $this->handleResponse($response, 'register_products');
    }

    public function increaseStock(string $tin, string $token, array $data, string $environment = 'sandbox'): array
    {
        $response = $this->client($token)
            ->withHeaders($this->environmentHeader($environment))
            ->post("{$this->baseUrl}/api/{$tin}/increase-stock", $data);

        return $this->handleResponse($response, 'increase_stock');
    }

    public function decreaseStock(string $tin, string $token, array $data, string $environment = 'sandbox'): array
    {
        $response = $this->client($token)
            ->withHeaders($this->environmentHeader($environment))
            ->post("{$this->baseUrl}/api/{$tin}/decrease-stock", $data);

        return $this->handleResponse($response, 'decrease_stock');
    }

    public function transferStock(string $tin, string $token, array $data, string $environment = 'sandbox'): array
    {
        $response = $this->client($token)
            ->withHeaders($this->environmentHeader($environment))
            ->post("{$this->baseUrl}/api/{$tin}/transfer-stock", $data);

        return $this->handleResponse($response, 'transfer_stock');
    }

    public function searchTaxpayer(string $tin, string $token, string $searchTin, string $environment = 'sandbox'): array
    {
        $response = $this->client($token)
            ->withHeaders($this->environmentHeader($environment))
            ->post("{$this->baseUrl}/api/{$tin}/search-taxpayer", [
                'tin' => $searchTin,
            ]);

        return $this->handleResponse($response, 'search_taxpayer');
    }

    public function getBranches(string $tin, string $token, string $environment = 'sandbox'): array
    {
        $response = $this->client($token)
            ->withHeaders($this->environmentHeader($environment))
            ->get("{$this->baseUrl}/api/{$tin}/branches");

        return $this->handleResponse($response, 'get_branches');
    }

    public function getMasterData(string $tin, string $token, string $environment = 'sandbox'): array
    {
        $response = $this->client($token)
            ->withHeaders($this->environmentHeader($environment))
            ->get("{$this->baseUrl}/api/{$tin}/master-data");

        return $this->handleResponse($response, 'get_master_data');
    }

    public function getExciseDuty(string $tin, string $token, string $environment = 'sandbox'): array
    {
        $response = $this->client($token)
            ->withHeaders($this->environmentHeader($environment))
            ->get("{$this->baseUrl}/api/{$tin}/excise-duty");

        return $this->handleResponse($response, 'get_excise_duty');
    }

    public function mapSaleToInvoice(array $sale, EfrisConfig $config): array
    {
        $items = [];
        foreach ($sale['items'] as $item) {
            $taxRate = $item['tax_rate'] ?? 0;
            $unitPrice = $item['unit_price'];
            $quantity = $item['quantity'];
            $total = $unitPrice * $quantity;
            $netAmount = $taxRate > 0 ? $total / (1 + $taxRate / 100) : $total;

            $items[] = [
                'itemCode' => $item['product_code'] ?? $item['sku'] ?? '',
                'quantity' => (string) $quantity,
                'unitPrice' => (string) number_format($unitPrice, 2, '.', ''),
                'total' => (string) number_format($total, 2, '.', ''),
                'taxForm' => (string) number_format($taxRate, 2),
                'taxRule' => $taxRate > 0 ? 'STANDARD' : 'EXEMPT',
                'netAmount' => (string) number_format($netAmount, 2, '.', ''),
                'discountFlag' => '2',
                'deemedFlag' => '2',
                'exciseFlag' => '2',
                'exciseCurrency' => 'UGX',
            ];
        }

        return [
            'data' => [
                'sellerDetails' => [
                    'placeOfBusiness' => $sale['branch_name'] ?? '',
                    'referenceNo' => $sale['invoice_number'] ?? '',
                    'issuedDate' => now()->format('Y-m-d'),
                ],
                'basicInformation' => [
                    'operator' => $sale['cashier_name'] ?? '',
                    'currency' => 'UGX',
                    'invoiceType' => 'B2C',
                    'invoiceKind' => $config->fiscalize_receipts ? '2' : '1',
                    'paymentMode' => $sale['payment_method'] ?? 'CASH',
                    'invoiceIndustryCode' => '01',
                ],
                'buyerDetails' => [
                    'buyerTin' => $sale['buyer_tin'] ?? '',
                    'buyerBusinessName' => $sale['buyer_name'] ?? 'Walk-in Customer',
                    'buyerAddress' => $sale['buyer_address'] ?? '',
                    'buyerEmail' => $sale['buyer_email'] ?? '',
                    'buyerLinePhone' => $sale['buyer_phone'] ?? '',
                    'buyerMobilePhone' => $sale['buyer_phone'] ?? '',
                    'buyerType' => '1',
                ],
                'itemsBought' => $items,
            ],
        ];
    }

    private function handleResponse($response, string $operation): array
    {
        if ($response->failed()) {
            Log::error("EFRIS API error: {$operation}", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => $response->body(),
                'status_code' => $response->status(),
            ];
        }

        $body = $response->json();

        $returnCode = $body['status']['returnCode'] ?? null;
        if ($returnCode !== null && $returnCode !== '00') {
            Log::warning("EFRIS API business error: {$operation}", [
                'return_code' => $returnCode,
                'return_message' => $body['status']['returnMessage'] ?? 'Unknown',
            ]);

            return [
                'success' => false,
                'error' => $body['status']['returnMessage'] ?? 'Unknown error',
                'return_code' => $returnCode,
                'data' => $body['data'] ?? null,
            ];
        }

        return [
            'success' => true,
            'data' => $body['data'] ?? $body,
            'status' => $body['status'] ?? null,
        ];
    }
}
