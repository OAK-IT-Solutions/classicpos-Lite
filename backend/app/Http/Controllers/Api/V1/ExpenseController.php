<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Branch;
use App\Models\OperatingAccount;
use App\Services\AccountingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class ExpenseController extends Controller
{
    public function __construct(
        protected AccountingService $accountingService,
    ) {}

    #[OA\Get(path: "/expenses", tags: ["Expenses"], summary: "List expenses", responses: [new OA\Response(response: 200, description: "Paginated expenses")])]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchIds = $user->branches()->pluck('branches.id')->toArray();
        if (empty($branchIds)) {
            $branchIds = [$user->branch_id];
        }

        $query = Expense::whereIn('branch_id', $branchIds)->orderByDesc('expense_date');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('method')) {
            $query->where('method', $request->method);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('expense_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('expense_date', '<=', $request->date_to);
        }
        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($w) use ($q) {
                $w->where('payee', 'like', "%{$q}%")
                  ->orWhere('reference', 'like', "%{$q}%")
                  ->orWhere('notes', 'like', "%{$q}%");
            });
        }

        $expenses = $query->paginate($request->per_page ?? 20);

        return response()->json($expenses);
    }

    #[OA\Post(path: "/expenses", tags: ["Expenses"], summary: "Record expense", responses: [new OA\Response(response: 201, description: "Expense recorded")])]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payee' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'method' => 'required|string|in:cash,bank_transfer,mobile_money,cheque',
            'category' => 'required|string|in:' . implode(',', Expense::$categories),
            'reference' => 'nullable|string|max:255',
            'expense_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
            'purchase_order_id' => 'nullable|uuid|exists:purchase_orders,id',
        ]);

        $validated['id'] = (string) Str::uuid();
        $validated['branch_id'] = $request->user()->branch_id;

        $expense = Expense::create($validated);

        $this->createExpenseJournalEntry($expense, $request->user()->id);

        return response()->json(['data' => $expense], 201);
    }

    public function show(string $id): JsonResponse
    {
        $expense = Expense::with('purchaseOrder')->findOrFail($id);
        return response()->json(['data' => $expense]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $expense = Expense::findOrFail($id);

        $validated = $request->validate([
            'payee' => 'sometimes|string|max:255',
            'amount' => 'sometimes|numeric|min:0',
            'method' => 'sometimes|string|in:cash,bank_transfer,mobile_money,cheque',
            'category' => 'sometimes|string|in:' . implode(',', Expense::$categories),
            'reference' => 'nullable|string|max:255',
            'expense_date' => 'sometimes|date',
            'notes' => 'nullable|string|max:1000',
            'purchase_order_id' => 'nullable|uuid|exists:purchase_orders,id',
        ]);

        $expense->update($validated);

        return response()->json(['data' => $expense->fresh()]);
    }

    public function destroy(string $id): JsonResponse
    {
        $expense = Expense::findOrFail($id);
        $expense->delete();

        return response()->json(['message' => 'Expense deleted.']);
    }

    private function createExpenseJournalEntry(Expense $expense, string $userId): void
    {
        $defaultOp = OperatingAccount::where('branch_id', $expense->branch_id)
            ->where('is_default', true)
            ->first();

        if (!$defaultOp) {
            Log::warning('No default operating account, skipping expense journal entry', ['branch_id' => $expense->branch_id]);
            return;
        }

        $expenseAccountCode = $this->getExpenseAccountCode($expense->category);

        try {
            $this->accountingService->createJournalEntry(
                branchId: $expense->branch_id,
                entryDate: $expense->expense_date->format('Y-m-d'),
                description: "Expense: {$expense->payee}",
                lines: [
                    [
                        'account_code' => $expenseAccountCode,
                        'debit' => (float) $expense->amount,
                        'credit' => 0,
                        'description' => $expense->notes ?? $expense->category,
                    ],
                    [
                        'account_id' => $defaultOp->account_id,
                        'debit' => 0,
                        'credit' => (float) $expense->amount,
                        'description' => 'Payment via ' . $expense->method,
                    ],
                ],
                referenceType: 'expense',
                referenceId: $expense->id,
                createdBy: $userId,
            );
        } catch (\Exception $e) {
            Log::error('Failed to create expense journal entry', [
                'expense_id' => $expense->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getExpenseAccountCode(string $category): string
    {
        return match ($category) {
            'Inventory Purchase' => '1330',
            'Rent' => '6100',
            'Utilities' => '6200',
            'Wages & Salaries' => '6300',
            'Maintenance' => '6500',
            'Transport' => '6700',
            'Marketing' => '6600',
            'Insurance' => '6800',
            'Licenses & Permits' => '7010',
            'Office Supplies' => '6400',
            'Professional Fees' => '6900',
            'Taxes' => '7020',
            default => '7220',
        };
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchIds = $user->branches()->pluck('branches.id')->toArray();
        if (empty($branchIds)) {
            $branchIds = [$user->branch_id];
        }

        $total = Expense::whereIn('branch_id', $branchIds)->sum('amount');
        $monthTotal = Expense::whereIn('branch_id', $branchIds)
            ->whereMonth('expense_date', now()->month)
            ->whereYear('expense_date', now()->year)
            ->sum('amount');
        $byCategory = Expense::whereIn('branch_id', $branchIds)
            ->selectRaw('category, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        return response()->json(['data' => [
            'total' => (float) $total,
            'month_total' => (float) $monthTotal,
            'by_category' => $byCategory,
        ]]);
    }
}
