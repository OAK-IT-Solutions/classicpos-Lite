<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\JournalEntryLine;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\GrnItem;
use App\Models\StockTransferItem;
use App\Models\ReturnItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/reports")]
class ReportController extends Controller
{
    private function getUserBranchIds(Request $request): array
    {
        $user = $request->user();
        $branchIds = $user->branches()->pluck('branches.id')->toArray();
        if (empty($branchIds)) {
            $branchIds = [$user->branch_id];
        }
        return $branchIds;
    }

    #[OA\Get(path: "/reports", tags: ["Reports"], summary: "Sales summary report", responses: [new OA\Response(response: 200, description: "Report data")])]
    public function summary(Request $request): JsonResponse
    {
        $branchIds = $this->getUserBranchIds($request);

        $totalSales = Sale::whereIn('branch_id', $branchIds)->count();
        $totalRevenue = Sale::whereIn('branch_id', $branchIds)->sum('total_amount');
        $todaySales = Sale::whereIn('branch_id', $branchIds)->whereDate('created_at', today())->count();
        $todayRevenue = Sale::whereIn('branch_id', $branchIds)->whereDate('created_at', today())->sum('total_amount');

        $totalProducts = Product::whereHas('inventory.warehouse', fn($q) => $q->whereIn('branch_id', $branchIds))->count();
        $activeProducts = Product::where('is_active', true)->whereHas('inventory.warehouse', fn($q) => $q->whereIn('branch_id', $branchIds))->count();
        $totalInventoryValue = DB::table('inventory')
            ->join('products', 'inventory.product_id', '=', 'products.id')
            ->join('warehouses', 'inventory.warehouse_id', '=', 'warehouses.id')
            ->whereIn('warehouses.branch_id', $branchIds)
            ->sum(DB::raw('inventory.quantity * products.price'));

        $lowStockCount = Inventory::whereIn('warehouse_id', fn($q) => $q->select('id')->from('warehouses')->whereIn('branch_id', $branchIds))
            ->where('quantity', '<=', DB::raw('COALESCE((SELECT min_stock FROM products WHERE products.id = inventory.product_id), 0)'))
            ->count();

        $totalCustomers = Customer::whereIn('branch_id', $branchIds)->count();

        return response()->json([
            'data' => [
                'sales' => [
                    'total_sales' => $totalSales,
                    'total_revenue' => (float) $totalRevenue,
                    'today_sales' => $todaySales,
                    'today_revenue' => (float) $todayRevenue,
                ],
                'inventory' => [
                    'total_products' => $totalProducts,
                    'active_products' => $activeProducts,
                    'total_value' => (float) $totalInventoryValue,
                    'low_stock_count' => $lowStockCount,
                ],
                'customers' => ['total' => $totalCustomers],
                'branches' => ['total' => count($branchIds)],
            ],
        ]);
    }

    #[OA\Get(path: "/reports/sales-trend", tags: ["Reports"], summary: "Sales trend over time", responses: [new OA\Response(response: 200, description: "Trend data")])]
    public function salesTrend(Request $request): JsonResponse
    {
        $branchIds = $this->getUserBranchIds($request);
        $days = min((int) $request->get('days', 30), 365);

        $trend = Sale::whereIn('branch_id', $branchIds)
            ->where('created_at', '>=', now()->subDays($days))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'), DB::raw('SUM(total_amount) as revenue'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        return response()->json(['data' => $trend]);
    }

    #[OA\Get(path: "/reports/top-products", tags: ["Reports"], summary: "Top selling products", responses: [new OA\Response(response: 200, description: "Top products")])]
    public function topProducts(Request $request): JsonResponse
    {
        $branchIds = $this->getUserBranchIds($request);
        $limit = min((int) $request->get('limit', 10), 50);

        $top = SaleItem::join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->whereIn('sales.branch_id', $branchIds)
            ->select('products.id', 'products.name',
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw('SUM(sale_items.quantity * sale_items.price) as total_revenue'))
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();

        return response()->json(['data' => $top]);
    }

    #[OA\Get(path: "/reports/revenue-by-payment", tags: ["Reports"], summary: "Revenue by payment method", responses: [new OA\Response(response: 200, description: "Payment breakdown")])]
    public function revenueByPayment(Request $request): JsonResponse
    {
        $branchIds = $this->getUserBranchIds($request);

        $data = Sale::whereIn('branch_id', $branchIds)
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_amount) as revenue'))
            ->groupBy('payment_method')
            ->get();

        return response()->json(['data' => $data]);
    }

    #[OA\Get(path: "/reports/daily-revenue", tags: ["Reports"], summary: "Daily revenue report", responses: [new OA\Response(response: 200, description: "Daily revenue")])]
    public function dailyRevenue(Request $request): JsonResponse
    {
        $branchIds = $this->getUserBranchIds($request);
        $days = min((int) $request->get('days', 30), 365);

        $data = Sale::whereIn('branch_id', $branchIds)
            ->where('created_at', '>=', now()->subDays($days))
            ->select(DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_amount) as revenue'),
                DB::raw('SUM(tax_amount) as tax'),
                DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        return response()->json(['data' => $data]);
    }

    #[OA\Get(path: "/reports/tax", tags: ["Reports"], summary: "Tax report", responses: [new OA\Response(response: 200, description: "Tax data")])]
    public function taxReport(Request $request): JsonResponse
    {
        $branchIds = $this->getUserBranchIds($request);
        $days = min((int) $request->get('days', 30), 365);

        $data = Sale::whereIn('branch_id', $branchIds)
            ->where('created_at', '>=', now()->subDays($days))
            ->select('invoice_number', 'created_at', 'total_amount', 'tax_amount', 'payment_method')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $data]);
    }

    #[OA\Get(path: "/reports/profit-loss", tags: ["Reports"], summary: "Profit and loss report", responses: [new OA\Response(response: 200, description: "P&L data")])]
    public function profitLoss(Request $request): JsonResponse
    {
        $branchIds = $this->getUserBranchIds($request);

        $totalRevenue = Sale::whereIn('branch_id', $branchIds)->sum('total_amount');
        $totalCost = SaleItem::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->whereIn('sales.branch_id', $branchIds)
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->sum(DB::raw('sale_items.quantity * products.cost'));
        $totalTax = Sale::whereIn('branch_id', $branchIds)->sum('tax_amount');

        return response()->json(['data' => [
            'total_revenue' => (float) $totalRevenue,
            'total_cost' => (float) $totalCost,
            'gross_profit' => (float) ($totalRevenue - $totalCost),
            'total_tax' => (float) $totalTax,
            'net_profit' => (float) ($totalRevenue - $totalCost - $totalTax),
            'margin_percent' => $totalRevenue > 0 ? round(($totalRevenue - $totalCost) / $totalRevenue * 100, 2) : 0,
        ]]);
    }

    #[OA\Get(path: "/reports/sales-journal", tags: ["Reports"], summary: "Sales journal", responses: [new OA\Response(response: 200, description: "Journal data")])]
    public function salesJournal(Request $request): JsonResponse
    {
        $branchIds = $this->getUserBranchIds($request);

        $data = Sale::whereIn('branch_id', $branchIds)
            ->with(['items.product', 'customer', 'branch'])
            ->orderByDesc('created_at')
            ->limit(500)
            ->get()
            ->flatMap(fn($sale) => $sale->items->map(fn($item) => [
                'date' => $sale->created_at->toISOString(),
                'invoice' => $sale->invoice_number,
                'product' => $item->product?->name ?? 'Unknown',
                'qty' => $item->quantity,
                'price' => (float) $item->price,
                'total' => (float) ($item->quantity * $item->price),
                'payment' => $sale->payment_method,
                'branch' => $sale->branch?->name ?? '',
            ]));

        return response()->json(['data' => $data]);
    }

    #[OA\Get(path: "/reports/inventory-movement", tags: ["Reports"], summary: "Inventory movement report", responses: [new OA\Response(response: 200, description: "Movement data")])]
    public function inventoryMovement(Request $request): JsonResponse
    {
        $branchIds = $this->getUserBranchIds($request);
        $days = min((int) $request->get('days', 30), 365);

        $grnIn = GrnItem::join('grn', 'grn_items.grn_id', '=', 'grn.id')
            ->join('purchase_orders', 'grn.purchase_order_id', '=', 'purchase_orders.id')
            ->whereIn('purchase_orders.branch_id', $branchIds)
            ->where('grn.created_at', '>=', now()->subDays($days))
            ->select(DB::raw("'GRN' as type"), 'grn_items.product_id', DB::raw('SUM(grn_items.quantity) as qty'), DB::raw('SUM(grn_items.quantity * grn_items.unit_cost) as value'))
            ->groupBy('grn_items.product_id');

        $saleOut = SaleItem::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->whereIn('sales.branch_id', $branchIds)
            ->where('sales.created_at', '>=', now()->subDays($days))
            ->select(DB::raw("'SALE' as type"), 'sale_items.product_id', DB::raw('SUM(sale_items.quantity) as qty'), DB::raw('SUM(sale_items.quantity * sale_items.price) as value'))
            ->groupBy('sale_items.product_id');

        $transferOut = StockTransferItem::join('stock_transfers', 'stock_transfer_items.stock_transfer_id', '=', 'stock_transfers.id')
            ->whereIn('stock_transfers.from_warehouse_id', fn($q) => $q->select('id')->from('warehouses')->whereIn('branch_id', $branchIds))
            ->where('stock_transfers.created_at', '>=', now()->subDays($days))
            ->select(DB::raw("'TRANSFER_OUT' as type"), 'stock_transfer_items.product_id', DB::raw('SUM(stock_transfer_items.quantity) as qty'), DB::raw('0 as value'))
            ->groupBy('stock_transfer_items.product_id');

        $grouped = $grnIn->unionAll($saleOut)->unionAll($transferOut)
            ->orderBy('product_id')
            ->get()
            ->groupBy('product_id');

        $productIds = $grouped->keys()->toArray();
        $products = Product::whereIn('id', $productIds)->pluck('name', 'id');

        $data = $grouped->map(fn($items, $pid) => [
            'product_id' => $pid,
            'product_name' => $products[$pid] ?? 'Unknown',
            'in_qty' => $items->where('type', 'GRN')->sum('qty') + $items->where('type', 'RETURN')->sum('qty'),
            'out_qty' => $items->where('type', 'SALE')->sum('qty') + $items->where('type', 'TRANSFER_OUT')->sum('qty'),
            'net' => $items->where('type', 'GRN')->sum('qty') + $items->where('type', 'RETURN')->sum('qty') - $items->where('type', 'SALE')->sum('qty') - $items->where('type', 'TRANSFER_OUT')->sum('qty'),
        ])->values();

        return response()->json(['data' => $data]);
    }

    #[OA\Get(path: "/reports/customer-statement", tags: ["Reports"], summary: "Customer statement report", responses: [new OA\Response(response: 200, description: "Customer data")])]
    public function customerStatement(Request $request): JsonResponse
    {
        $branchIds = $this->getUserBranchIds($request);

        $data = Customer::whereIn('branch_id', $branchIds)
            ->with(['sales' => fn($q) => $q->orderByDesc('created_at')->limit(50)])
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'total_spend' => (float) $c->sales->sum('total_amount'),
                'total_visits' => $c->sales->count(),
                'loyalty_points' => $c->loyalty_points,
                'last_purchase' => $c->sales->first()?->created_at?->toISOString(),
            ]);

        return response()->json(['data' => $data]);
    }

    #[OA\Get(path: "/reports/sales-by-user", tags: ["Reports"], summary: "Sales by user", responses: [new OA\Response(response: 200, description: "User sales data")])]
    public function salesByUser(Request $request): JsonResponse
    {
        $branchIds = $this->getUserBranchIds($request);

        $data = User::whereIn('branch_id', $branchIds)
            ->withCount(['sales' => fn($q) => $q->where('status', '!=', 'voided')])
            ->withSum(['sales' => fn($q) => $q->where('status', '!=', 'voided')], 'total_amount')
            ->orderByDesc('sales_count')
            ->get()
            ->map(fn($user) => [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'total_sales' => (int) $user->sales_count,
                'total_revenue' => (float) ($user->sales_sum_total_amount ?? 0),
            ]);

        return response()->json(['data' => $data]);
    }

    #[OA\Get(path: "/reports/cogs", tags: ["Reports"], summary: "Cost of goods sold", responses: [new OA\Response(response: 200, description: "COGS data")])]
    public function costOfGoodsSold(Request $request): JsonResponse
    {
        $branchIds = $this->getUserBranchIds($request);

        $data = SaleItem::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->whereIn('sales.branch_id', $branchIds)
            ->where('sales.status', '!=', 'voided')
            ->select(
                DB::raw('DATE(sales.created_at) as date'),
                DB::raw('SUM(sale_items.quantity * products.cost) as cogs'),
                DB::raw('SUM(sale_items.quantity * sale_items.price) as revenue'),
            )
            ->groupBy(DB::raw('DATE(sales.created_at)'))
            ->orderBy('date')
            ->get()
            ->map(fn($r) => [
                'date' => $r->date,
                'cogs' => (float) $r->cogs,
                'revenue' => (float) $r->revenue,
                'gross_profit' => (float) ($r->revenue - $r->cogs),
                'margin_pct' => $r->revenue > 0 ? round(($r->revenue - $r->cogs) / $r->revenue * 100, 2) : 0,
            ]);

        return response()->json(['data' => $data]);
    }

    #[OA\Get(path: "/reports/payments-reconciliation", tags: ["Reports"], summary: "Payments reconciliation", responses: [new OA\Response(response: 200, description: "Reconciliation data")])]
    public function paymentsReconciliation(Request $request): JsonResponse
    {
        $branchIds = $this->getUserBranchIds($request);

        $data = Sale::whereIn('branch_id', $branchIds)
            ->select('payment_method',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total_amount) as total'),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END) as completed"),
                DB::raw("SUM(CASE WHEN status = 'voided' THEN total_amount ELSE 0 END) as voided"))
            ->groupBy('payment_method')
            ->get();

        return response()->json(['data' => $data]);
    }

    #[OA\Get(path: "/reports/trial-balance", tags: ["Reports"], summary: "Trial balance", responses: [new OA\Response(response: 200, description: "Trial balance data")])]
    public function trialBalance(Request $request): JsonResponse
    {
        $branchIds = $this->getUserBranchIds($request);

        $accounts = ChartOfAccount::whereIn('branch_id', $branchIds)
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(function ($account) use ($branchIds) {
                $totalDebits = JournalEntryLine::whereHas('journalEntry', fn($q) => $q->whereIn('branch_id', $branchIds))
                    ->where('account_id', $account->id)
                    ->sum('debit_amount');

                $totalCredits = JournalEntryLine::whereHas('journalEntry', fn($q) => $q->whereIn('branch_id', $branchIds))
                    ->where('account_id', $account->id)
                    ->sum('credit_amount');

                $balance = $account->normal_balance === 'debit'
                    ? (float) ($totalDebits - $totalCredits)
                    : (float) ($totalCredits - $totalDebits);

                return [
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'group' => $account->group,
                    'normal_balance' => $account->normal_balance,
                    'debit' => $balance >= 0 && $account->normal_balance === 'debit' ? $balance : 0,
                    'credit' => $balance >= 0 && $account->normal_balance === 'credit' ? $balance : 0,
                    'balance' => $balance,
                ];
            });

        $totalDebits = $accounts->sum('debit');
        $totalCredits = $accounts->sum('credit');

        return response()->json(['data' => [
            'accounts' => $accounts,
            'totals' => [
                'debit' => round($totalDebits, 2),
                'credit' => round($totalCredits, 2),
            ],
        ]]);
    }

    #[OA\Get(path: "/reports/balance-sheet", tags: ["Reports"], summary: "Balance sheet", responses: [new OA\Response(response: 200, description: "Balance sheet data")])]
    public function balanceSheet(Request $request): JsonResponse
    {
        $branchIds = $this->getUserBranchIds($request);

        $accounts = ChartOfAccount::whereIn('branch_id', $branchIds)
            ->where('is_active', true)
            ->whereIn('type', ['asset', 'liability', 'equity'])
            ->orderBy('code')
            ->get()
            ->map(function ($account) use ($branchIds) {
                $totalDebits = JournalEntryLine::whereHas('journalEntry', fn($q) => $q->whereIn('branch_id', $branchIds))
                    ->where('account_id', $account->id)
                    ->sum('debit_amount');

                $totalCredits = JournalEntryLine::whereHas('journalEntry', fn($q) => $q->whereIn('branch_id', $branchIds))
                    ->where('account_id', $account->id)
                    ->sum('credit_amount');

                $balance = $account->normal_balance === 'debit'
                    ? (float) ($totalDebits - $totalCredits)
                    : (float) ($totalCredits - $totalDebits);

                return [
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'group' => $account->group,
                    'balance' => $balance,
                ];
            });

        $assets = $accounts->whereIn('type', ['asset'])->sum('balance');
        $liabilities = $accounts->whereIn('type', ['liability'])->sum('balance');
        $equity = $accounts->whereIn('type', ['equity'])->sum('balance');

        return response()->json(['data' => [
            'assets' => [
                'total' => round($assets, 2),
                'accounts' => $accounts->where('type', 'asset')->values(),
            ],
            'liabilities' => [
                'total' => round($liabilities, 2),
                'accounts' => $accounts->where('type', 'liability')->values(),
            ],
            'equity' => [
                'total' => round($equity, 2),
                'accounts' => $accounts->where('type', 'equity')->values(),
            ],
            'total_liabilities_equity' => round($liabilities + $equity, 2),
        ]]);
    }

    #[OA\Get(path: "/reports/income-statement", tags: ["Reports"], summary: "Income statement", responses: [new OA\Response(response: 200, description: "Income statement data")])]
    public function incomeStatement(Request $request): JsonResponse
    {
        $branchIds = $this->getUserBranchIds($request);
        $dateFrom = $request->get('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->format('Y-m-d'));

        $accounts = ChartOfAccount::whereIn('branch_id', $branchIds)
            ->where('is_active', true)
            ->whereIn('type', ['revenue', 'expense'])
            ->orderBy('code')
            ->get()
            ->map(function ($account) use ($branchIds, $dateFrom, $dateTo) {
                $totalDebits = JournalEntryLine::whereHas('journalEntry', fn($q) => $q->whereIn('branch_id', $branchIds)->whereBetween('entry_date', [$dateFrom, $dateTo]))
                    ->where('account_id', $account->id)
                    ->sum('debit_amount');

                $totalCredits = JournalEntryLine::whereHas('journalEntry', fn($q) => $q->whereIn('branch_id', $branchIds)->whereBetween('entry_date', [$dateFrom, $dateTo]))
                    ->where('account_id', $account->id)
                    ->sum('credit_amount');

                $balance = $account->normal_balance === 'debit'
                    ? (float) ($totalDebits - $totalCredits)
                    : (float) ($totalCredits - $totalDebits);

                return [
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'group' => $account->group,
                    'balance' => $balance,
                ];
            });

        $totalRevenue = $accounts->where('type', 'revenue')->sum('balance');
        $totalExpenses = $accounts->where('type', 'expense')->sum('balance');
        $netIncome = $totalRevenue - $totalExpenses;

        return response()->json(['data' => [
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'revenue' => [
                'total' => round($totalRevenue, 2),
                'accounts' => $accounts->where('type', 'revenue')->values(),
            ],
            'expenses' => [
                'total' => round($totalExpenses, 2),
                'accounts' => $accounts->where('type', 'expense')->values(),
            ],
            'net_income' => round($netIncome, 2),
        ]]);
    }

    #[OA\Get(path: "/reports/general-ledger", tags: ["Reports"], summary: "General ledger", responses: [new OA\Response(response: 200, description: "Ledger data")])]
    public function generalLedger(Request $request): JsonResponse
    {
        $branchIds = $this->getUserBranchIds($request);
        $accountId = $request->get('account_id');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $query = JournalEntryLine::with([
            'journalEntry' => fn($q) => $q->whereIn('branch_id', $branchIds),
            'account',
        ]);

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        if ($dateFrom) {
            $query->whereHas('journalEntry', fn($q) => $q->where('entry_date', '>=', $dateFrom));
        }

        if ($dateTo) {
            $query->whereHas('journalEntry', fn($q) => $q->where('entry_date', '<=', $dateTo));
        }

        $lines = $query->orderBy('created_at')->paginate($request->per_page ?? 50);

        return response()->json($lines);
    }

    #[OA\Get(path: "/reports/low-stock", tags: ["Reports"], summary: "Low stock report", responses: [new OA\Response(response: 200, description: "Low stock items")])]
    public function lowStock(Request $request): JsonResponse
    {
        $branchIds = $this->getUserBranchIds($request);

        $data = Inventory::with('product', 'warehouse')
            ->whereIn('warehouse_id', fn($q) => $q->select('id')->from('warehouses')->whereIn('branch_id', $branchIds))
            ->where('quantity', '<=', DB::raw('COALESCE((SELECT min_stock FROM products WHERE products.id = inventory.product_id), 0)'))
            ->get()
            ->map(fn($i) => [
                'product_name' => $i->product?->name ?? 'Unknown',
                'warehouse' => $i->warehouse?->name ?? '',
                'quantity' => (float) $i->quantity,
                'min_stock' => (int) ($i->product?->min_stock ?? 0),
            ]);

        return response()->json(['data' => $data]);
    }

    #[OA\Get(path: "/reports/inventory-valuation", tags: ["Reports"], summary: "Inventory valuation", responses: [new OA\Response(response: 200, description: "Valuation data")])]
    public function inventoryValuation(Request $request): JsonResponse
    {
        $branchIds = $this->getUserBranchIds($request);

        $data = Inventory::with('product', 'warehouse')
            ->whereIn('warehouse_id', fn($q) => $q->select('id')->from('warehouses')->whereIn('branch_id', $branchIds))
            ->where('quantity', '>', 0)
            ->get()
            ->groupBy('product_id')
            ->map(fn($items, $pid) => [
                'product_name' => $items->first()->product?->name ?? 'Unknown',
                'quantity' => (float) $items->sum('quantity'),
                'price' => (float) ($items->first()->product?->cost ?? 0),
                'value' => (float) $items->sum(fn($i) => $i->quantity * ($i->product?->cost ?? 0)),
            ])
            ->values();

        return response()->json(['data' => $data]);
    }

    #[OA\Get(path: "/reports/purchase-orders", tags: ["Reports"], summary: "Purchase orders report", responses: [new OA\Response(response: 200, description: "PO data")])]
    public function purchaseOrders(Request $request): JsonResponse
    {
        $branchIds = $this->getUserBranchIds($request);

        $data = PurchaseOrder::with('supplier')
            ->whereIn('branch_id', $branchIds)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn($po) => [
                'po_number' => $po->po_number,
                'supplier' => $po->supplier?->name ?? '',
                'status' => $po->status,
                'total_amount' => (float) $po->total_amount,
                'created_at' => $po->created_at->toISOString(),
            ]);

        return response()->json(['data' => $data]);
    }

    #[OA\Get(path: "/reports/loyalty-points", tags: ["Reports"], summary: "Loyalty points report", responses: [new OA\Response(response: 200, description: "Loyalty data")])]
    public function loyaltyPoints(Request $request): JsonResponse
    {
        $branchIds = $this->getUserBranchIds($request);

        $data = Customer::whereIn('branch_id', $branchIds)
            ->orderByDesc('loyalty_points')
            ->get()
            ->map(fn($c) => [
                'name' => $c->name,
                'phone' => $c->phone,
                'loyalty_points' => (int) ($c->loyalty_points ?? 0),
                'member_level' => $c->member_level ?? 'Regular',
                'total_spend' => (float) ($c->total_spend ?? 0),
            ]);

        return response()->json(['data' => $data]);
    }
}
