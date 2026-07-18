<?php

namespace App\Services;

use App\Models\EfrisConfig;
use App\Models\EfrisFiscalLog;
use App\Models\Integration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IntegrationService
{
    public function __construct(
        private EfrisService $efrisService,
    ) {}

    public function getAvailableIntegrations(): array
    {
        return [
            [
                'type' => 'efris',
                'name' => 'URA EFRIS',
                'description' => 'Uganda Revenue Authority Electronic Fiscal Receipting and Invoicing System via WEAF API',
                'icon' => 'Receipt',
                'category' => 'tax',
                'requires_config' => ['weaf_email', 'weaf_password', 'tin'],
            ],
        ];
    }

    public function getForBranch(string $branchId, string $type): ?Integration
    {
        return Integration::where('branch_id', $branchId)
            ->where('type', $type)
            ->with('efrisConfig')
            ->first();
    }

    public function getConnectedForBranch(string $branchId): \Illuminate\Database\Eloquent\Collection
    {
        return Integration::where('branch_id', $branchId)
            ->with('efrisConfig')
            ->get();
    }

    public function connect(string $branchId, string $type, array $config): Integration
    {
        return DB::transaction(function () use ($branchId, $type, $config) {
            $integration = Integration::updateOrCreate(
                ['branch_id' => $branchId, 'type' => $type],
                [
                    'name' => $config['name'] ?? $type,
                    'status' => 'pending',
                    'config' => array_filter($config, fn ($v, $k) => !in_array($k, ['weaf_password', 'tin', 'weaf_email', 'weaf_environment', 'auto_fiscalize', 'fiscalize_receipts']), ARRAY_FILTER_USE_BOTH),
                ]
            );

            if ($type === 'efris') {
                $this->setupEfris($integration, $config);
            }

            return $integration->fresh('efrisConfig');
        });
    }

    private function setupEfris(Integration $integration, array $config): void
    {
        $email = $config['weaf_email'] ?? '';
        $password = $config['weaf_password'] ?? '';
        $tin = $config['tin'] ?? '';
        $environment = $config['weaf_environment'] ?? 'sandbox';

        $tokenResult = $this->efrisService->generateToken($email, $password, 30, 'ClassicPOS');

        if (!$tokenResult['success']) {
            $integration->update([
                'status' => 'error',
                'last_error' => $tokenResult['error'] ?? 'Failed to authenticate with WEAF',
            ]);
            throw new \RuntimeException('Failed to authenticate with WEAF API: ' . ($tokenResult['error'] ?? 'Unknown error'));
        }

        $token = $tokenResult['data']['token'] ?? '';
        $expiresAt = $tokenResult['data']['expires_at'] ?? now()->addDays(30)->toIso8601String();
        $companies = $tokenResult['data']['companies'] ?? [];
        $companyName = $companies[0]['business_name'] ?? null;
        $companyWeafId = $companies[0]['id'] ?? null;

        EfrisConfig::updateOrCreate(
            ['integration_id' => $integration->id],
            [
                'branch_id' => $integration->branch_id,
                'tin' => $tin,
                'weaf_email' => $email,
                'weaf_token' => $token,
                'weaf_token_expires_at' => $expiresAt,
                'weaf_environment' => $environment,
                'company_name' => $companyName,
                'company_weaf_id' => $companyWeafId,
                'auto_fiscalize' => $config['auto_fiscalize'] ?? true,
                'fiscalize_receipts' => $config['fiscalize_receipts'] ?? true,
            ]
        );

        $integration->update([
            'status' => 'active',
            'last_error' => null,
        ]);
    }

    public function disconnect(string $integrationId): bool
    {
        return DB::transaction(function () use ($integrationId) {
            $integration = Integration::findOrFail($integrationId);

            if ($integration->type === 'efris') {
                EfrisConfig::where('integration_id', $integrationId)->delete();
            }

            $integration->delete();
            return true;
        });
    }

    public function testConnection(string $integrationId): array
    {
        $integration = Integration::with('efrisConfig')->findOrFail($integrationId);

        if ($integration->type !== 'efris') {
            return ['success' => false, 'error' => 'Testing not supported for this integration type'];
        }

        $config = $integration->efrisConfig;
        if (!$config) {
            return ['success' => false, 'error' => 'EFRIS configuration not found'];
        }

        if ($config->isTokenExpired()) {
            $this->refreshEfrisToken($config);
        }

        $result = $this->efrisService->getRegistrationDetails(
            $config->tin,
            $config->weaf_token,
            $config->weaf_environment
        );

        if ($result['success']) {
            $integration->update(['status' => 'active', 'last_error' => null]);
            return [
                'success' => true,
                'message' => 'Connection successful',
                'registration' => $result['data'] ?? null,
            ];
        }

        $integration->update(['status' => 'error', 'last_error' => $result['error'] ?? 'Connection test failed']);
        return ['success' => false, 'error' => $result['error'] ?? 'Connection test failed'];
    }

    public function refreshEfrisToken(EfrisConfig $config): bool
    {
        $result = $this->efrisService->refreshToken($config->weaf_token, 60, 'ClassicPOS Refresh');

        if ($result['success']) {
            $config->update([
                'weaf_token' => $result['data']['new_token'],
                'weaf_token_expires_at' => $result['data']['expires_at'],
            ]);
            return true;
        }

        Log::error('Failed to refresh EFRIS token', ['config_id' => $config->id, 'error' => $result['error']]);
        return false;
    }

    public function ensureValidToken(EfrisConfig $config): ?string
    {
        if ($config->isTokenExpired()) {
            $refreshed = $this->refreshEfrisToken($config);
            if (!$refreshed) {
                return null;
            }
            $config->refresh();
        }

        return $config->weaf_token;
    }

    public function fiscalizeSale($sale, string $branchId): array
    {
        $integration = $this->getForBranch($branchId, 'efris');

        if (!$integration || !$integration->isActive()) {
            return ['success' => false, 'error' => 'EFRIS integration not active for this branch'];
        }

        $config = $integration->efrisConfig;
        if (!$config) {
            return ['success' => false, 'error' => 'EFRIS configuration not found'];
        }

        $token = $this->ensureValidToken($config);
        if (!$token) {
            return ['success' => false, 'error' => 'Failed to obtain valid EFRIS token'];
        }

        $payload = $this->efrisService->mapSaleToInvoice($sale, $config);

        $log = EfrisFiscalLog::create([
            'branch_id' => $branchId,
            'sale_id' => $sale['id'] ?? null,
            'request_payload' => $payload,
            'status' => EfrisFiscalLog::STATUS_PENDING,
        ]);

        try {
            $method = $config->fiscalize_receipts ? 'generateFiscalReceipt' : 'generateFiscalInvoice';
            $result = $this->efrisService->$method(
                $config->tin,
                $token,
                $payload,
                $config->weaf_environment
            );

            if ($result['success']) {
                $log->markSuccess($result['data']);
                $integration->update(['last_sync_at' => now()]);
                return [
                    'success' => true,
                    'fdn' => $log->efris_fdn,
                    'qr_code' => $log->efris_qr_code,
                    'verification_code' => $log->efris_verification_code,
                    'invoice_no' => $log->efris_invoice_no,
                ];
            }

            $log->markFailed($result['error'] ?? 'Fiscalization failed');
            return ['success' => false, 'error' => $result['error'], 'log_id' => $log->id];
        } catch (\Exception $e) {
            $log->markFailed($e->getMessage());
            Log::error('EFRIS fiscalization exception', ['error' => $e->getMessage(), 'sale_id' => $sale['id'] ?? null]);
            return ['success' => false, 'error' => $e->getMessage(), 'log_id' => $log->id];
        }
    }

    public function processOfflineQueue(string $branchId): array
    {
        $pendingLogs = EfrisFiscalLog::where('branch_id', $branchId)
            ->whereIn('status', [EfrisFiscalLog::STATUS_PENDING, EfrisFiscalLog::STATUS_OFFLINE_QUEUED])
            ->where('retry_count', '<', 5)
            ->orderBy('created_at')
            ->limit(50)
            ->get();

        $results = ['processed' => 0, 'succeeded' => 0, 'failed' => 0];

        foreach ($pendingLogs as $log) {
            $results['processed']++;

            $integration = $this->getForBranch($branchId, 'efris');
            if (!$integration || !$integration->isActive()) {
                $log->markFailed('EFRIS integration not active');
                $results['failed']++;
                continue;
            }

            $config = $integration->efrisConfig;
            $token = $this->ensureValidToken($config);

            if (!$token) {
                $log->markFailed('Failed to obtain valid token');
                $results['failed']++;
                continue;
            }

            try {
                $method = $config->fiscalize_receipts ? 'generateFiscalReceipt' : 'generateFiscalInvoice';
                $result = $this->efrisService->$method(
                    $config->tin,
                    $token,
                    $log->request_payload,
                    $config->weaf_environment
                );

                if ($result['success']) {
                    $log->markSuccess($result['data']);
                    $results['succeeded']++;
                } else {
                    $log->markFailed($result['error'] ?? 'Fiscalization failed');
                    $results['failed']++;
                }
            } catch (\Exception $e) {
                $log->markFailed($e->getMessage());
                $results['failed']++;
            }
        }

        return $results;
    }
}
