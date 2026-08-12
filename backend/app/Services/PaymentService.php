<?php

namespace App\Services;

use App\Events\PaymentFailed;
use App\Models\Inventory;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    private ?LocalQueueService $queue = null;

    public function __construct(
        protected PaymentGatewayManager $gatewayManager,
    ) {
        // Use LocalQueueService when not connected to Redis
        if (config('queue.default') !== 'redis' || !$this->isRedisAvailable()) {
            $this->queue = app(LocalQueueService::class);
        }
    }

    private function isRedisAvailable(): bool
    {
        try {
            $ping = \Illuminate\Support\Facades\Redis::ping();
            return $ping === true || $ping === 'PONG' || $ping === '+PONG';
        } catch (\Exception) {
            return false;
        }
    }

    public function processPayment(array $paymentData)
    {
        $result = $this->attemptGatewayPayment($paymentData);

        DB::beginTransaction();
        try {
            if ($result['success'] && $this->isOnline()) {
                $payment = $this->completePayment($paymentData, $result);
                DB::commit();
                return $payment;
            }

            $payment = $this->queueForOfflineProcessing($paymentData, $result);
            DB::commit();
            return $payment;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment transaction failed: ' . $e->getMessage());

            return $this->recordFailedPayment($paymentData, $e->getMessage());
        }
    }

    public function refund(string $saleId, float $amount, string $returnId): array
    {
        DB::beginTransaction();
        try {
            $sale = Sale::findOrFail($saleId);
            $originalPayment = $sale->payments()->where('status', Payment::STATUS_COMPLETED)->first();

            if (!$originalPayment) {
                throw new \RuntimeException('No completed payment found for sale to refund.');
            }

            $gatewayResult = ['success' => true, 'refund_transaction_id' => null];
            if ($originalPayment->gateway) {
                $gatewayResult = $this->gatewayManager->refund(
                    $originalPayment->gateway,
                    $originalPayment->txn_id,
                    $amount,
                    ['sale_id' => $saleId, 'return_id' => $returnId],
                );
            }

            if (!$gatewayResult['success']) {
                throw new \RuntimeException($gatewayResult['error'] ?? 'Gateway refund failed.');
            }

            $refundPayment = Payment::create([
                'id' => (string) Str::uuid(),
                'sale_id' => $saleId,
                'amount' => -$amount,
                'method' => $originalPayment->method,
                'gateway' => $originalPayment->gateway,
                'txn_id' => $gatewayResult['refund_transaction_id'] ?? ('refund_' . uniqid()),
                'status' => Payment::STATUS_REFUNDED,
            ]);

            $totalRefunded = abs($sale->payments()
                ->where('status', Payment::STATUS_REFUNDED)
                ->sum('amount'));

            if ($totalRefunded >= $sale->total_amount) {
                $sale->update(['status' => Sale::STATUS_REFUNDED]);
            }

            DB::commit();

            Log::info('Payment refunded', [
                'sale_id' => $saleId,
                'amount' => $amount,
                'refund_payment_id' => $refundPayment->id,
                'return_id' => $returnId,
            ]);

            return [
                'success' => true,
                'refund_payment_id' => $refundPayment->id,
                'amount' => $amount,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Refund failed: ' . $e->getMessage(), [
                'sale_id' => $saleId,
                'amount' => $amount,
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function attemptGatewayPayment(array $paymentData): array
    {
        $gatewayName = $paymentData['gateway'] ?? '';

        if (empty($gatewayName)) {
            return [
                'success' => true,
                'transaction_id' => 'cash_' . uniqid(),
                'amount' => $paymentData['amount'],
                'method' => $paymentData['payment_method'] ?? 'cash',
                'error' => null,
            ];
        }

        return $this->gatewayManager->charge(
            $gatewayName,
            $paymentData['amount'],
            $paymentData,
        );
    }

    private function isOnline(): bool
    {
        // In offline mode, always queue for later processing
        if (config('queue.default') === 'sync') {
            return false;
        }

        try {
            $ping = \Illuminate\Support\Facades\Redis::ping();
            return $ping === true || $ping === 'PONG' || $ping === '+PONG';
        } catch (\Exception) {
            return false;
        }
    }

    private function completePayment(array $paymentData, array $gatewayResponse)
    {
        $payment = Payment::create([
            'id' => (string) Str::uuid(),
            'sale_id' => $paymentData['sale_id'],
            'amount' => $paymentData['amount'],
            'method' => $paymentData['payment_method'],
            'gateway' => $paymentData['gateway'] ?? null,
            'txn_id' => $gatewayResponse['transaction_id'],
            'status' => Payment::STATUS_COMPLETED,
        ]);

        Sale::where('id', $paymentData['sale_id'])->update(['status' => 'completed']);

        Log::info('Payment processed', [
            'payment_id' => $payment->id,
            'sale_id' => $paymentData['sale_id'],
            'amount' => $paymentData['amount'],
            'gateway' => $paymentData['gateway'] ?? 'cash',
            'txn_id' => $gatewayResponse['transaction_id'],
        ]);

        return $payment;
    }

    private function queueForOfflineProcessing(array $paymentData, array $gatewayResponse)
    {
        $paymentRecord = [
            'sale_id' => $paymentData['sale_id'],
            'amount' => $paymentData['amount'],
            'method' => $paymentData['payment_method'],
            'gateway' => $paymentData['gateway'] ?? null,
            'status' => 'pending',
            'attempted_at' => now()->toISOString(),
            'gateway_response' => $gatewayResponse,
        ];

        if ($this->queue) {
            $this->queue->push('payment_queue', json_encode($paymentRecord));
        } else {
            \Illuminate\Support\Facades\Redis::lpush('payment_queue', json_encode($paymentRecord));
        }

        $payment = Payment::create([
            'id' => (string) Str::uuid(),
            'sale_id' => $paymentData['sale_id'],
            'amount' => $paymentData['amount'],
            'method' => $paymentData['payment_method'],
            'gateway' => $paymentData['gateway'] ?? null,
            'status' => 'pending',
        ]);

        Log::warning('Payment queued for offline processing', [
            'payment_id' => $payment->id,
            'sale_id' => $paymentData['sale_id'],
            'amount' => $paymentData['amount'],
        ]);

        return $payment;
    }

    private function recordFailedPayment(array $paymentData, string $errorMessage)
    {
        $payment = Payment::create([
            'id' => (string) Str::uuid(),
            'sale_id' => $paymentData['sale_id'],
            'amount' => $paymentData['amount'],
            'method' => $paymentData['payment_method'],
            'gateway' => $paymentData['gateway'] ?? null,
            'status' => 'failed',
        ]);

        Sale::where('id', $paymentData['sale_id'])->update(['status' => 'payment_failed']);

        Log::error('Payment failed', [
            'payment_id' => $payment->id,
            'sale_id' => $paymentData['sale_id'],
            'amount' => $paymentData['amount'],
            'error' => $errorMessage,
        ]);

        $sale = Sale::find($paymentData['sale_id']);
        if ($sale) {
            PaymentFailed::dispatch($sale, $errorMessage);
        }

        return $payment;
    }

    public function processOfflineQueue(): array
    {
        $processed = 0;
        $failed = 0;

        if ($this->queue) {
            // SQLite-backed queue processing
            // Recover any orphaned items from a previous crash
            while ($this->queue->length('payment_queue_processing') > 0) {
                $this->queue->move('payment_queue_processing', 'payment_queue');
            }

            while ($this->queue->length('payment_queue') > 0) {
                $paymentJson = $this->queue->move('payment_queue', 'payment_queue_processing');

                if (!$paymentJson) {
                    continue;
                }

                $paymentData = json_decode($paymentJson, true);

                if (is_null($paymentData)) {
                    $this->queue->remove('payment_queue_processing', $paymentJson);
                    Log::error('Invalid payment data in queue: ' . $paymentJson);
                    continue;
                }

                try {
                    $result = $this->attemptGatewayPayment($paymentData);

                    DB::beginTransaction();

                    if ($result['success']) {
                        $this->completePayment($paymentData, $result);
                        $processed++;
                        DB::commit();
                        $this->queue->remove('payment_queue_processing', $paymentJson);
                    } else {
                        $attemptCount = $paymentData['attempt_count'] ?? 0;
                        if ($attemptCount < 3) {
                            $paymentData['attempt_count'] = $attemptCount + 1;
                            DB::commit();
                            $this->queue->remove('payment_queue_processing', $paymentJson);
                            $this->queue->push('payment_queue', json_encode($paymentData));
                            Log::warning('Payment retry queued', [
                                'sale_id' => $paymentData['sale_id'],
                                'attempt' => $paymentData['attempt_count'],
                            ]);
                        } else {
                            $this->recordFailedPayment($paymentData, 'Max retry attempts exceeded');
                            $failed++;
                            DB::commit();
                            $this->queue->remove('payment_queue_processing', $paymentJson);
                        }
                    }
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Error processing offline payment: ' . $e->getMessage());
                    $failed++;
                }
            }
        } else {
            // Redis-backed queue processing (original code)
            while (\Illuminate\Support\Facades\Redis::llen('payment_queue_processing') > 0) {
                $paymentJson = \Illuminate\Support\Facades\Redis::rpoplpush('payment_queue_processing', 'payment_queue');
            }

            while (\Illuminate\Support\Facades\Redis::llen('payment_queue') > 0) {
                $paymentJson = \Illuminate\Support\Facades\Redis::rpoplpush('payment_queue', 'payment_queue_processing');

                if (!$paymentJson) {
                    continue;
                }

                $paymentData = json_decode($paymentJson, true);

                if (is_null($paymentData)) {
                    \Illuminate\Support\Facades\Redis::lrem('payment_queue_processing', 1, $paymentJson);
                    Log::error('Invalid payment data in queue: ' . $paymentJson);
                    continue;
                }

                try {
                    $result = $this->attemptGatewayPayment($paymentData);

                    DB::beginTransaction();

                    if ($result['success']) {
                        $this->completePayment($paymentData, $result);
                        $processed++;
                        DB::commit();
                        \Illuminate\Support\Facades\Redis::lrem('payment_queue_processing', 1, $paymentJson);
                    } else {
                        $attemptCount = $paymentData['attempt_count'] ?? 0;
                        if ($attemptCount < 3) {
                            $paymentData['attempt_count'] = $attemptCount + 1;
                            DB::commit();
                            \Illuminate\Support\Facades\Redis::lrem('payment_queue_processing', 1, $paymentJson);
                            \Illuminate\Support\Facades\Redis::lpush('payment_queue', json_encode($paymentData));
                            Log::warning('Payment retry queued', [
                                'sale_id' => $paymentData['sale_id'],
                                'attempt' => $paymentData['attempt_count'],
                            ]);
                        } else {
                            $this->recordFailedPayment($paymentData, 'Max retry attempts exceeded');
                            $failed++;
                            DB::commit();
                            \Illuminate\Support\Facades\Redis::lrem('payment_queue_processing', 1, $paymentJson);
                        }
                    }
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Error processing offline payment: ' . $e->getMessage());
                    $failed++;
                }
            }
        }

        Log::info('Offline queue processing complete', [
            'processed' => $processed,
            'failed' => $failed,
        ]);

        return ['processed' => $processed, 'failed' => $failed];
    }

    public function rollbackTransaction(string $saleId): bool
    {
        DB::beginTransaction();
        try {
            $sale = Sale::find($saleId);
            if (!$sale) {
                throw new \Exception("Sale not found: $saleId");
            }

            $warehouse = Warehouse::where('branch_id', $sale->branch_id)
                ->where('is_active', true)
                ->first();

            if (!$warehouse) {
                throw new \RuntimeException(
                    "No active warehouse found for branch {$sale->branch_id} during rollback."
                );
            }

            foreach ($sale->items as $item) {
                $record = Inventory::where('product_id', $item->product_id)
                    ->where('warehouse_id', $warehouse->id)
                    ->lockForUpdate()
                    ->first();

                if ($record) {
                    $record->increment('quantity', $item->quantity);
                    $record->refresh();

                    StockMovement::create([
                        'id' => (string) Str::uuid(),
                        'inventory_id' => $record->id,
                        'product_id' => $item->product_id,
                        'warehouse_id' => $warehouse->id,
                        'quantity_change' => $item->quantity,
                        'running_balance' => $record->quantity,
                        'reference_type' => 'rollback',
                        'reference_id' => $saleId,
                    ]);
                }
            }

            $payment = $sale->payments()->where('status', 'completed')->first();
            if ($payment) {
                $payment->update(['status' => 'voided']);
            }

            $sale->update(['status' => 'voided']);

            DB::commit();
            Log::info('Transaction rolled back', ['sale_id' => $saleId]);
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to rollback transaction: ' . $e->getMessage());
            return false;
        }
    }
}
