<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/payments")]
class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    #[OA\Get(path: "/payments", tags: ["Payments"], summary: "List payments", responses: [new OA\Response(response: 200, description: "Paginated payments")])]
    public function index(Request $request): JsonResponse
    {
        $query = Payment::query()->with('sale:id,invoice_number');

        if ($request->filled('sale_id')) {
            $query->where('sale_id', $request->sale_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('method')) {
            $query->where('method', $request->method);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $payments = $query->orderByDesc('created_at')->paginate($request->per_page ?? 15);

        return response()->json($payments);
    }

    #[OA\Post(path: "/payments", tags: ["Payments"], summary: "Process a payment", responses: [new OA\Response(response: 201, description: "Payment processed")])]
    public function process(Request $request)
    {
        $validated = $request->validate([
            'sale_id' => 'required|exists:sales,id',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,mobile_money,card,qr',
            'gateway' => 'nullable|in:MTN MoMo,Airtel Money,M-Pesa,card,qr',
        ]);

        try {
            $payment = $this->paymentService->processPayment($validated);

            return response()->json([
                'payment_id' => $payment->id,
                'sale_id' => $payment->sale_id,
                'amount' => $payment->amount,
                'status' => $payment->status,
                'method' => $payment->method,
                'txn_id' => $payment->txn_id,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'code' => 'ERR_PAYMENT_FAILED',
                    'message' => $e->getMessage(),
                    'details' => [],
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 400);
        }
    }

    #[OA\Post(path: "/payments/{saleId}/rollback", tags: ["Payments"], summary: "Rollback a payment", responses: [new OA\Response(response: 200, description: "Payment rolled back")])]
    public function rollback(string $saleId)
    {
        $result = $this->paymentService->rollbackTransaction($saleId);

        if (!$result) {
            return response()->json([
                'error' => [
                    'code' => 'ERR_ROLLBACK_FAILED',
                    'message' => 'Failed to rollback transaction for sale: ' . $saleId,
                    'details' => [],
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 400);
        }

        return response()->json(['message' => 'Transaction rolled back successfully.']);
    }
}
