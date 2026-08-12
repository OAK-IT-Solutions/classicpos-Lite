<?php

namespace App\Services;

use App\Events\SaleCompleted;
use App\Events\SaleVoided;
use App\Http\Resources\SaleResource;
use App\Mail\SaleInvoice;
use App\Models\HoldSale;
use App\Models\OperatingAccount;
use App\Models\Sale;
use App\Models\Warehouse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PosService
{
    private ?LocalQueueService $queue = null;

    public function __construct(
        protected PromotionService $promotionService,
        protected TaxService $taxService,
        protected LoyaltyService $loyaltyService,
        protected PaymentService $paymentService,
        protected InventoryService $inventoryService,
        protected AccountingService $accountingService,
        protected IntegrationService $integrationService,
    ) {
        if (config('queue.default') !== 'redis') {
            $this->queue = app(LocalQueueService::class);
        }
    }

    public function createSale(array $data): array
    {
        $result = DB::transaction(function () use ($data) {
            $warehouseIds = Warehouse::where('branch_id', $data['branch_id'])
                ->where('is_active', true)
                ->pluck('id');

            if ($warehouseIds->isEmpty()) {
                throw new \RuntimeException('No active warehouse found for this branch.');
            }

            $subtotal = 0;
            foreach ($data['items'] as $item) {
                $this->inventoryService->reserveStock(
                    $item['product_id'],
                    $data['branch_id'],
                    $item['quantity']
                );

                $subtotal += $item['quantity'] * $item['price'];
            }

            $promoResult = $this->promotionService->validateAndApply(
                $data['promo_code'] ?? null,
                $subtotal
            );

            if (!empty($promoResult['error'])) {
                throw new \RuntimeException($promoResult['error']);
            }

            $discountAmount = $promoResult['discount'];
            $afterDiscount = $subtotal - $discountAmount;

            $loyaltyDiscount = 0;
            if (!empty($data['loyalty_points_redeemed']) && !empty($data['customer_id'])) {
                $customer = \App\Models\Customer::lockForUpdate()->find($data['customer_id']);
                if ($customer && $customer->loyalty_points >= $data['loyalty_points_redeemed']) {
                    $rule = \App\Models\LoyaltyRule::where('is_active', true)->first();
                    if ($rule && $rule->reward_thresholds) {
                        foreach ($rule->reward_thresholds as $threshold) {
                            if (($threshold['points'] ?? 0) === (int) $data['loyalty_points_redeemed']) {
                                $loyaltyDiscount = $threshold['value'] ?? 0;
                                break;
                            }
                        }
                    }
                    $customer->decrement('loyalty_points', $data['loyalty_points_redeemed']);
                }
            }

            $taxProfileId = $data['tax_profile_id'] ?? null;
            $totalTaxAmount = 0;
            $itemTaxData = [];

            foreach ($data['items'] as $item) {
                $itemSubtotal = $item['quantity'] * $item['price'];
                $itemTaxResult = $this->taxService->calculateItemTax($taxProfileId, $itemSubtotal);
                $itemTaxData[] = [
                    'tax_rate' => $itemTaxResult['tax_rate'],
                    'tax_amount' => $itemTaxResult['tax_amount'],
                ];
                $totalTaxAmount += $itemTaxResult['tax_amount'];
            }

            $totalAmount = max(0, $afterDiscount + $totalTaxAmount - $loyaltyDiscount);

            $invoiceNumber = 'INV-' . strtoupper(substr((string) Str::uuid(), 0, 8));

            $sale = Sale::create([
                'id' => (string) Str::uuid(),
                'branch_id' => $data['branch_id'],
                'customer_id' => $data['customer_id'] ?? null,
                'invoice_number' => $invoiceNumber,
                'total_amount' => round($totalAmount, 2),
                'tax_amount' => round($totalTaxAmount, 2),
                'discount' => round($discountAmount + $loyaltyDiscount, 2),
                'payment_method' => $data['payment_method'],
                'status' => 'pending_sync',
            ]);

            foreach ($data['items'] as $index => $item) {
                $tax = $itemTaxData[$index] ?? ['tax_rate' => 0, 'tax_amount' => 0];
                $sale->items()->create([
                    'id' => (string) Str::uuid(),
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'subtotal' => $item['quantity'] * $item['price'],
                    'tax_rate' => $tax['tax_rate'],
                    'tax_amount' => $tax['tax_amount'],
                ]);

                $this->inventoryService->fulfillReservation(
                    $item['product_id'],
                    $data['branch_id'],
                    $item['quantity'],
                    'sale',
                    $sale->id,
                );
            }

            if (!empty($data['customer_id'])) {
                $this->loyaltyService->awardPoints($data['customer_id'], $afterDiscount);
            }

            $this->createSaleJournalEntry(
                branchId: $data['branch_id'],
                sale: $sale,
                subtotal: $subtotal,
                afterDiscount: $afterDiscount,
                discountAmount: $discountAmount + $loyaltyDiscount,
                totalTaxAmount: $totalTaxAmount,
                totalAmount: $totalAmount,
                userId: $data['user_id'] ?? null,
            );

            return [
                'sale' => $sale,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'loyalty_discount' => $loyaltyDiscount,
                'total_tax_amount' => $totalTaxAmount,
            ];
        });

        /** @var Sale $sale */
        $sale = $result['sale'];

        $syncPayload = json_encode([
            'event' => 'SALE_CREATED',
            'data' => $sale->load('items'),
            'timestamp' => now()->toISOString(),
        ]);

        if ($this->queue) {
            $this->queue->push('sync_queue', $syncPayload);
        } else {
            \Illuminate\Support\Facades\Redis::lpush('sync_queue', $syncPayload);
        }

        try {
            $this->paymentService->processPayment([
                'sale_id' => $sale->id,
                'amount' => $sale->total_amount,
                'payment_method' => $data['payment_method'],
                'gateway' => $data['gateway'] ?? null,
            ]);
            $sale->refresh();
        } catch (\Exception $e) {
            Log::warning('Payment processing deferred for sale', [
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
            ]);
            $sale->update(['status' => Sale::STATUS_PAYMENT_FAILED]);
        }

        if ($sale->status === Sale::STATUS_COMPLETED) {
            SaleCompleted::dispatch($sale, $data['branch_id']);
        }

        // EFRIS fiscalization — async, non-blocking
        try {
            $this->fiscalizeSaleEfris($sale, $data['branch_id']);
        } catch (\Exception $e) {
            Log::warning('EFRIS fiscalization deferred for sale', [
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'sale_id' => $sale->id,
            'invoice_number' => $sale->invoice_number,
            'subtotal' => round($result['subtotal'], 2),
            'discount' => round($result['discount_amount'] + $result['loyalty_discount'], 2),
            'tax_amount' => round($result['total_tax_amount'], 2),
            'total_amount' => round($sale->total_amount, 2),
            'loyalty_discount' => round($result['loyalty_discount'], 2),
            'payment_method' => $sale->payment_method,
            'status' => $sale->status,
            'created_at' => $sale->created_at->toISOString(),
        ];
    }

    public function getSalesList(array $filters): array
    {
        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        $sales = Sale::query()
            ->when(!empty($filters['branch_id']), fn($q) => $q->where('branch_id', $filters['branch_id']))
            ->when(!empty($filters['date_from']), fn($q) => $q->whereDate('created_at', '>=', $filters['date_from']))
            ->when(!empty($filters['date_to']), fn($q) => $q->whereDate('created_at', '<=', $filters['date_to']))
            ->when(!empty($filters['search']), fn($q) => $q->whereRaw('LOWER(invoice_number) LIKE ?', ['%' . strtolower($filters['search']) . '%']))
            ->when(!empty($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->when(!empty($filters['payment_method']), fn($q) => $q->where('payment_method', $filters['payment_method']))
            ->when(!empty($filters['customer_id']), fn($q) => $q->where('customer_id', $filters['customer_id']))
            ->when(isset($filters['min_amount']), fn($q) => $q->where('total_amount', '>=', $filters['min_amount']))
            ->when(isset($filters['max_amount']), fn($q) => $q->where('total_amount', '<=', $filters['max_amount']))
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return [
            'data' => SaleResource::collection($sales->items()),
            'current_page' => $sales->currentPage(),
            'last_page' => $sales->lastPage(),
            'per_page' => $sales->perPage(),
            'total' => $sales->total(),
        ];
    }

    public function getSaleDetail(string $saleId): SaleResource
    {
        $sale = Sale::with(['customer', 'items.product', 'payments'])->findOrFail($saleId);

        return new SaleResource($sale);
    }

    public function voidSale(string $saleId): void
    {
        DB::transaction(function () use ($saleId) {
            $sale = Sale::with('items')->lockForUpdate()->findOrFail($saleId);

            if (!in_array($sale->status, [Sale::STATUS_PENDING, Sale::STATUS_PENDING_SYNC, Sale::STATUS_COMPLETED])) {
                throw new \RuntimeException('Only pending, pending_sync, or completed sales can be voided.');
            }

            foreach ($sale->items as $item) {
                $this->inventoryService->restock(
                    $item->product_id,
                    $sale->branch_id,
                    $item->quantity,
                    'sale_void',
                    $saleId,
                );
            }

            $payment = $sale->payments()->where('status', 'completed')->first();
            if ($payment) {
                $payment->update(['status' => 'voided']);
            }

            $sale->update(['status' => Sale::STATUS_VOIDED]);

            Log::info('Sale voided', ['sale_id' => $saleId]);
        });

        SaleVoided::dispatch($sale->fresh(), $sale->branch_id);
    }

    public function emailInvoice(string $saleId): void
    {
        $sale = Sale::with(['customer', 'items.product'])->findOrFail($saleId);

        if (!$sale->customer || !$sale->customer->email) {
            throw new \RuntimeException('Sale has no customer email address.');
        }

        Mail::to($sale->customer->email)
            ->queue(new SaleInvoice($sale));

        Log::info('Invoice emailed', [
            'sale_id' => $saleId,
            'email' => $sale->customer->email,
        ]);
    }

    public function holdSale(array $data): HoldSale
    {
        $holdSale = HoldSale::create([
            'id' => (string) Str::uuid(),
            'branch_id' => $data['branch_id'],
            'user_id' => $data['user_id'],
            'customer_id' => $data['customer_id'] ?? null,
            'cart_data' => $data['cart_data'],
            'promo_code' => $data['promo_code'] ?? null,
            'tax_profile_id' => $data['tax_profile_id'] ?? null,
            'loyalty_points_redeemed' => $data['loyalty_points_redeemed'] ?? 0,
            'note' => $data['note'] ?? null,
        ]);

        Log::info('Sale held', [
            'hold_sale_id' => $holdSale->id,
            'branch_id' => $data['branch_id'],
        ]);

        return $holdSale;
    }

    public function getHeldSales(string $branchId, ?string $userId = null): array
    {
        $query = HoldSale::with(['customer:id,name'])
            ->where('branch_id', $branchId);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $heldSales = $query->orderByDesc('created_at')->get();

        return $heldSales->toArray();
    }

    public function resumeSale(string $holdSaleId): array
    {
        $holdSale = HoldSale::with(['customer:id,name,phone,email,loyalty_points,member_level'])
            ->findOrFail($holdSaleId);

        $holdSale->delete();

        Log::info('Sale resumed', ['hold_sale_id' => $holdSaleId]);

        return [
            'cart_data' => $holdSale->cart_data,
            'customer' => $holdSale->customer,
            'promo_code' => $holdSale->promo_code,
            'tax_profile_id' => $holdSale->tax_profile_id,
            'loyalty_points_redeemed' => $holdSale->loyalty_points_redeemed,
        ];
    }

    private function fiscalizeSaleEfris(Sale $sale, string $branchId): void
    {
        $integration = $this->integrationService->getForBranch($branchId, 'efris');

        if (!$integration || !$integration->isActive()) {
            return;
        }

        $config = $integration->efrisConfig;
        if (!$config || !$config->auto_fiscalize) {
            return;
        }

        $sale->load(['items.product', 'customer', 'branch']);

        $saleData = [
            'id' => $sale->id,
            'invoice_number' => $sale->invoice_number,
            'branch_name' => $sale->branch->name ?? '',
            'cashier_name' => 'POS',
            'payment_method' => $sale->payment_method,
            'buyer_tin' => $sale->customer?->tin ?? '',
            'buyer_name' => $sale->customer?->name ?? 'Walk-in Customer',
            'buyer_address' => $sale->customer?->address ?? '',
            'buyer_email' => $sale->customer?->email ?? '',
            'buyer_phone' => $sale->customer?->phone ?? '',
            'items' => $sale->items->map(fn ($item) => [
                'product_code' => $item->product?->barcode ?? '',
                'unit_price' => $item->price,
                'quantity' => $item->quantity,
                'tax_rate' => $item->tax_rate ?? 0,
            ])->toArray(),
        ];

        $result = $this->integrationService->fiscalizeSale($saleData, $branchId);

        if ($result['success']) {
            $sale->update([
                'efris_fdn' => $result['fdn'],
                'efris_qr_code' => $result['qr_code'],
                'efris_verification_code' => $result['verification_code'],
                'efris_fiscal_status' => 'success',
            ]);
            Log::info('Sale fiscalized with EFRIS', [
                'sale_id' => $sale->id,
                'fdn' => $result['fdn'],
            ]);
        } else {
            $sale->update(['efris_fiscal_status' => 'failed']);
            Log::warning('EFRIS fiscalization failed for sale', [
                'sale_id' => $sale->id,
                'error' => $result['error'],
            ]);
        }
    }

    private function createSaleJournalEntry(
        string $branchId,
        Sale $sale,
        float $subtotal,
        float $afterDiscount,
        float $discountAmount,
        float $totalTaxAmount,
        float $totalAmount,
        ?string $userId = null,
    ): void {
        $defaultOp = OperatingAccount::where('branch_id', $branchId)
            ->where('is_default', true)
            ->first();

        if (!$defaultOp) {
            Log::warning('No default operating account for branch, skipping sale journal entry', ['branch_id' => $branchId]);
            return;
        }

        $isCredit = $sale->payment_method === 'credit';

        $lines = [
            [
                'account_id' => $defaultOp->account_id,
                'debit' => $totalAmount,
                'credit' => 0,
                'description' => 'Sale receipt',
            ],
            [
                'account_code' => '4100',
                'debit' => 0,
                'credit' => $afterDiscount,
                'description' => 'Sales revenue',
            ],
        ];

        if ($totalTaxAmount > 0) {
            $lines[] = [
                'account_code' => '2140',
                'debit' => 0,
                'credit' => $totalTaxAmount,
                'description' => 'Sales tax collected',
            ];
        }

        if ($discountAmount > 0) {
            $lines[] = [
                'account_code' => '4300',
                'debit' => $discountAmount,
                'credit' => 0,
                'description' => 'Sales discounts',
            ];
        }

        try {
            $this->accountingService->createJournalEntry(
                branchId: $branchId,
                entryDate: now()->format('Y-m-d'),
                description: "Sale {$sale->invoice_number}",
                lines: $lines,
                referenceType: 'sale',
                referenceId: $sale->id,
                createdBy: $userId,
            );
        } catch (\Exception $e) {
            Log::error('Failed to create sale journal entry', [
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
