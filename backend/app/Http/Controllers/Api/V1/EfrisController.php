<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EfrisFiscalLog;
use App\Models\Integration;
use App\Services\EfrisService;
use App\Services\IntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/efris")]
class EfrisController extends Controller
{
    public function __construct(
        private EfrisService $efrisService,
        private IntegrationService $integrationService,
    ) {}

    private function getConfig(Request $request): \App\Models\EfrisConfig
    {
        $branchId = $request->user()->branch_id;
        $integration = $this->integrationService->getForBranch($branchId, 'efris');

        if (!$integration || !$integration->isActive() || !$integration->efrisConfig) {
            abort(400, 'EFRIS integration is not active for this branch');
        }

        return $integration->efrisConfig;
    }

    #[OA\Post(path: "/efris/fiscalize-sale/{saleId}", tags: ["EFRIS"], summary: "Fiscalize a sale", responses: [new OA\Response(response: 200, description: "Fiscalization result")])]
    public function fiscalizeSale(Request $request, string $saleId): JsonResponse
    {
        $config = $this->getConfig($request);

        $sale = \App\Models\Sale::with(['items.product', 'customer', 'branch'])->findOrFail($saleId);

        $saleData = [
            'id' => $sale->id,
            'invoice_number' => $sale->invoice_number,
            'branch_name' => $sale->branch->name ?? '',
            'cashier_name' => $request->user()->name,
            'payment_method' => $sale->payments->first()->method ?? 'CASH',
            'buyer_tin' => $sale->customer->tin ?? '',
            'buyer_name' => $sale->customer->name ?? 'Walk-in Customer',
            'buyer_address' => $sale->customer->address ?? '',
            'buyer_email' => $sale->customer->email ?? '',
            'buyer_phone' => $sale->customer->phone ?? '',
            'items' => $sale->items->map(fn ($item) => [
                'product_code' => $item->product->barcode ?? '',
                'unit_price' => $item->unit_price,
                'quantity' => $item->quantity,
                'tax_rate' => $item->tax_rate ?? 0,
            ])->toArray(),
        ];

        $result = $this->integrationService->fiscalizeSale($saleData, $sale->branch_id);

        if ($result['success']) {
            return response()->json([
                'data' => [
                    'fdn' => $result['fdn'],
                    'qr_code' => $result['qr_code'],
                    'verification_code' => $result['verification_code'],
                    'invoice_no' => $result['invoice_no'],
                ],
                'message' => 'Sale fiscalized successfully',
            ]);
        }

        return response()->json([
            'error' => [
                'code' => 'EFRIS_FISCALIZATION_FAILED',
                'message' => $result['error'],
                'log_id' => $result['log_id'] ?? null,
            ],
        ], 400);
    }

    #[OA\Post(path: "/efris/query-invoices", tags: ["EFRIS"], summary: "Query invoices from EFRIS", responses: [new OA\Response(response: 200, description: "Invoice list")])]
    public function queryInvoices(Request $request): JsonResponse
    {
        $config = $this->getConfig($request);

        $validated = $request->validate([
            'invoiceKind' => 'required|string',
            'pageNo' => 'required|string',
            'pageSize' => 'required|string',
            'startDate' => 'nullable|date_format:Y-m-d',
            'endDate' => 'nullable|date_format:Y-m-d',
            'buyerLegalName' => 'nullable|string',
        ]);

        $result = $this->efrisService->queryInvoices(
            $config->tin,
            $config->weaf_token,
            $validated,
            $config->weaf_environment
        );

        if ($result['success']) {
            return response()->json(['data' => $result['data']]);
        }

        return response()->json(['error' => ['message' => $result['error']]], 400);
    }

    #[OA\Get(path: "/efris/invoice/{invoiceNo}", tags: ["EFRIS"], summary: "Get invoice details from EFRIS", responses: [new OA\Response(response: 200, description: "Invoice details")])]
    public function invoiceDetails(Request $request, string $invoiceNo): JsonResponse
    {
        $config = $this->getConfig($request);

        $result = $this->efrisService->getInvoiceDetails(
            $config->tin,
            $config->weaf_token,
            $invoiceNo,
            $config->weaf_environment
        );

        if ($result['success']) {
            return response()->json(['data' => $result['data']]);
        }

        return response()->json(['error' => ['message' => $result['error']]], 400);
    }

    #[OA\Post(path: "/efris/credit-note", tags: ["EFRIS"], summary: "Apply credit note via EFRIS", responses: [new OA\Response(response: 200, description: "Credit note applied")])]
    public function applyCreditNote(Request $request): JsonResponse
    {
        $config = $this->getConfig($request);

        $validated = $request->validate([
            'generalInfo.oriInvoiceNo' => 'required|string',
            'generalInfo.reasonCode' => 'required|in:101,102,103,104,105',
            'generalInfo.reason' => 'required|string',
            'generalInfo.sellersReferenceNo' => 'required|string',
        ]);

        $validated['generalInfo']['invoiceApplyCategoryCode'] = '101';

        $result = $this->efrisService->applyCreditNote(
            $config->tin,
            $config->weaf_token,
            $validated,
            $config->weaf_environment
        );

        if ($result['success']) {
            return response()->json(['data' => $result['data'], 'message' => 'Credit note applied successfully']);
        }

        return response()->json(['error' => ['message' => $result['error']]], 400);
    }

    #[OA\Post(path: "/efris/sync-products", tags: ["EFRIS"], summary: "Sync products with EFRIS", responses: [new OA\Response(response: 200, description: "Product list")])]
    public function syncProducts(Request $request): JsonResponse
    {
        $config = $this->getConfig($request);

        $validated = $request->validate([
            'goodsCode' => 'nullable|string',
            'goodsName' => 'nullable|string',
            'pageSize' => 'nullable|string',
            'pageNo' => 'nullable|string',
        ]);

        $result = $this->efrisService->syncProducts(
            $config->tin,
            $config->weaf_token,
            array_merge(['goodsCode' => '', 'goodsName' => '', 'pageSize' => '20', 'pageNo' => '1'], $validated),
            $config->weaf_environment
        );

        if ($result['success']) {
            return response()->json(['data' => $result['data']]);
        }

        return response()->json(['error' => ['message' => $result['error']]], 400);
    }

    #[OA\Post(path: "/efris/register-products", tags: ["EFRIS"], summary: "Register products with EFRIS", responses: [new OA\Response(response: 200, description: "Products registered")])]
    public function registerProducts(Request $request): JsonResponse
    {
        $config = $this->getConfig($request);

        $validated = $request->validate([
            'products' => 'required|array|min:1',
            'products.*.goodsName' => 'required|string',
            'products.*.goodsCode' => 'required|string',
            'products.*.measureUnit' => 'required|string',
            'products.*.unitPrice' => 'required|string',
            'products.*.currency' => 'required|string',
            'products.*.commodityCategoryId' => 'required|string',
            'products.*.haveExciseTax' => 'required|string',
            'products.*.description' => 'required|string',
            'products.*.stockPrewarning' => 'required|string',
            'products.*.havePieceUnit' => 'required|string',
        ]);

        $result = $this->efrisService->registerProducts(
            $config->tin,
            $config->weaf_token,
            $validated['products'],
            $config->weaf_environment
        );

        if ($result['success']) {
            return response()->json(['data' => $result['data'], 'message' => 'Products registered successfully']);
        }

        return response()->json(['error' => ['message' => $result['error']]], 400);
    }

    #[OA\Post(path: "/efris/increase-stock", tags: ["EFRIS"], summary: "Increase stock via EFRIS", responses: [new OA\Response(response: 200, description: "Stock increased")])]
    public function increaseStock(Request $request): JsonResponse
    {
        $config = $this->getConfig($request);

        $validated = $request->validate([
            'stockInDate' => 'required|date_format:Y-m-d',
            'stockInType' => 'required|in:101,102,103,104',
            'stockInItem' => 'required|array|min:1',
            'stockInItem.*.itemCode' => 'required|string',
            'stockInItem.*.quantity' => 'required|string',
            'stockInItem.*.unitPrice' => 'required|string',
            'supplierName' => 'nullable|string',
            'supplierTin' => 'nullable|string',
            'remarks' => 'nullable|string',
            'branchId' => 'nullable|string',
        ]);

        $result = $this->efrisService->increaseStock(
            $config->tin,
            $config->weaf_token,
            $validated,
            $config->weaf_environment
        );

        if ($result['success']) {
            return response()->json(['data' => $result['data'], 'message' => 'Stock increased successfully']);
        }

        return response()->json(['error' => ['message' => $result['error']]], 400);
    }

    #[OA\Post(path: "/efris/decrease-stock", tags: ["EFRIS"], summary: "Decrease stock via EFRIS", responses: [new OA\Response(response: 200, description: "Stock decreased")])]
    public function decreaseStock(Request $request): JsonResponse
    {
        $config = $this->getConfig($request);

        $validated = $request->validate([
            'stockInItem' => 'required|array|min:1',
            'stockInItem.*.itemCode' => 'required|string',
            'stockInItem.*.quantity' => 'required|string',
            'stockInItem.*.unitPrice' => 'required|string',
            'adjustType' => 'required|in:101,102,103,104,105',
            'remarks' => 'nullable|string',
            'branchId' => 'nullable|string',
        ]);

        $result = $this->efrisService->decreaseStock(
            $config->tin,
            $config->weaf_token,
            $validated,
            $config->weaf_environment
        );

        if ($result['success']) {
            return response()->json(['data' => $result['data'], 'message' => 'Stock decreased successfully']);
        }

        return response()->json(['error' => ['message' => $result['error']]], 400);
    }

    #[OA\Post(path: "/efris/transfer-stock", tags: ["EFRIS"], summary: "Transfer stock via EFRIS", responses: [new OA\Response(response: 200, description: "Stock transferred")])]
    public function transferStock(Request $request): JsonResponse
    {
        $config = $this->getConfig($request);

        $validated = $request->validate([
            'goodsStockTransfer' => 'required|array',
            'goodsStockTransfer.sourceBranchId' => 'required|string',
            'goodsStockTransfer.destinationBranchId' => 'required|string',
            'goodsStockTransfer.transferTypeCode' => 'required|string',
            'goodsStockTransferItem' => 'required|array|min:1',
            'goodsStockTransferItem.*.itemCode' => 'required|string',
            'goodsStockTransferItem.*.quantity' => 'required|string',
        ]);

        $result = $this->efrisService->transferStock(
            $config->tin,
            $config->weaf_token,
            $validated,
            $config->weaf_environment
        );

        if ($result['success']) {
            return response()->json(['data' => $result['data'], 'message' => 'Stock transferred successfully']);
        }

        return response()->json(['error' => ['message' => $result['error']]], 400);
    }

    #[OA\Post(path: "/efris/search-taxpayer", tags: ["EFRIS"], summary: "Search taxpayer by TIN", responses: [new OA\Response(response: 200, description: "Taxpayer details")])]
    public function searchTaxpayer(Request $request): JsonResponse
    {
        $config = $this->getConfig($request);

        $validated = $request->validate([
            'search_tin' => 'required|string',
        ]);

        $result = $this->efrisService->searchTaxpayer(
            $config->tin,
            $config->weaf_token,
            $validated['search_tin'],
            $config->weaf_environment
        );

        if ($result['success']) {
            return response()->json(['data' => $result['data']]);
        }

        return response()->json(['error' => ['message' => $result['error']]], 400);
    }

    #[OA\Get(path: "/efris/registration-details", tags: ["EFRIS"], summary: "Get EFRIS registration details", responses: [new OA\Response(response: 200, description: "Registration details")])]
    public function registrationDetails(Request $request): JsonResponse
    {
        $config = $this->getConfig($request);

        $result = $this->efrisService->getRegistrationDetails(
            $config->tin,
            $config->weaf_token,
            $config->weaf_environment
        );

        if ($result['success']) {
            return response()->json(['data' => $result['data']]);
        }

        return response()->json(['error' => ['message' => $result['error']]], 400);
    }

    #[OA\Get(path: "/efris/logs", tags: ["EFRIS"], summary: "Get EFRIS fiscal logs", responses: [new OA\Response(response: 200, description: "Paginated fiscal logs")])]
    public function fiscalLogs(Request $request): JsonResponse
    {
        $branchId = $request->user()->branch_id;

        $logs = EfrisFiscalLog::where('branch_id', $branchId)
            ->with('sale')
            ->orderBy('created_at', 'desc')
            ->paginate(min((int) $request->get('per_page', 20), 100));

        return response()->json($logs);
    }
}
