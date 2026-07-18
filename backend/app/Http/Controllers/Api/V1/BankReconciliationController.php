<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\BankReconciliation;
use App\Models\ReconciliationItem;
use App\Models\OperatingAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/bank-reconciliation")]
class BankReconciliationController extends BaseController
{
    protected string $modelClass = BankReconciliation::class;

    protected array $searchableFields = ['notes'];

    protected array $withRelations = ['operatingAccount', 'operatingAccount.account', 'items', 'items.journalEntry', 'createdBy'];

    protected function rules(Request $request, ?string $id = null): array
    {
        return [
            'operating_account_id' => 'required|uuid|exists:operating_accounts,id',
            'statement_date' => 'required|date',
            'statement_balance' => 'required|numeric',
            'notes' => 'nullable|string',
        ];
    }

    protected function beforeStore(Request $request, array $validated): array
    {
        $account = OperatingAccount::findOrFail($validated['operating_account_id']);

        $validated['branch_id'] = $request->user()->branch_id;
        $validated['ledger_balance'] = $account->current_balance;
        $validated['difference'] = $validated['statement_balance'] - $account->current_balance;
        $validated['status'] = 'draft';
        $validated['created_by'] = $request->user()->id;

        return $validated;
    }

    protected function additionalQuery(Request $request, $query): void
    {
        $branchIds = $request->user()->branches->pluck('id')->push($request->user()->branch_id)->unique();
        $query->whereIn('branch_id', $branchIds);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($operatingAccountId = $request->get('operating_account_id')) {
            $query->where('operating_account_id', $operatingAccountId);
        }
    }

    #[OA\Get(path: "/bank-reconciliation", tags: ["Accounting"], summary: "List bank reconciliations", responses: [new OA\Response(response: 200, description: "Paginated reconciliations")])]
    public function index(Request $request): JsonResponse
    {
        return parent::index($request);
    }

    #[OA\Post(path: "/bank-reconciliation", tags: ["Accounting"], summary: "Create a bank reconciliation", responses: [new OA\Response(response: 201, description: "Reconciliation created")])]
    public function store(Request $request): JsonResponse
    {
        return parent::store($request);
    }

    #[OA\Get(path: "/bank-reconciliation/{id}", tags: ["Accounting"], summary: "Get a bank reconciliation", responses: [new OA\Response(response: 200, description: "Reconciliation details")])]
    public function show(string $id): JsonResponse
    {
        return parent::show($id);
    }

    #[OA\Post(path: "/bank-reconciliation/{id}/complete", tags: ["Accounting"], summary: "Complete a bank reconciliation", responses: [new OA\Response(response: 200, description: "Reconciliation completed")])]
    public function complete(string $id): JsonResponse
    {
        $reconciliation = BankReconciliation::findOrFail($id);

        if ($reconciliation->status === 'completed') {
            return response()->json(['error' => ['code' => 'ERR_ALREADY_COMPLETED', 'message' => 'Reconciliation is already completed.']], 400);
        }

        $reconciliation->update([
            'status' => 'completed',
            'reconciled_at' => now(),
        ]);

        $reconciliation->load($this->withRelations);

        return response()->json(['data' => $reconciliation]);
    }

    #[OA\Post(path: "/bank-reconciliation/{id}/items", tags: ["Accounting"], summary: "Add item to bank reconciliation", responses: [new OA\Response(response: 201, description: "Item added")])]
    public function addItem(Request $request, string $id): JsonResponse
    {
        $reconciliation = BankReconciliation::findOrFail($id);

        if ($reconciliation->status === 'completed') {
            return response()->json(['error' => ['code' => 'ERR_COMPLETED', 'message' => 'Cannot modify a completed reconciliation.']], 400);
        }

        $validated = $request->validate([
            'journal_entry_id' => 'required|uuid|exists:journal_entries,id',
            'amount' => 'required|numeric',
            'type' => 'required|string|in:cleared,outstanding_deposit,outstanding_check,bank_error,book_error',
            'is_cleared' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        $item = ReconciliationItem::create([
            'reconciliation_id' => $reconciliation->id,
            'journal_entry_id' => $validated['journal_entry_id'],
            'amount' => $validated['amount'],
            'type' => $validated['type'],
            'is_cleared' => $validated['is_cleared'] ?? ($validated['type'] === 'cleared'),
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json(['data' => $item], 201);
    }

    #[OA\Delete(path: "/bank-reconciliation/{id}/items/{itemId}", tags: ["Accounting"], summary: "Remove item from bank reconciliation", responses: [new OA\Response(response: 200, description: "Item removed")])]
    public function removeItem(string $id, string $itemId): JsonResponse
    {
        $reconciliation = BankReconciliation::findOrFail($id);

        if ($reconciliation->status === 'completed') {
            return response()->json(['error' => ['code' => 'ERR_COMPLETED', 'message' => 'Cannot modify a completed reconciliation.']], 400);
        }

        $item = ReconciliationItem::where('reconciliation_id', $reconciliation->id)
            ->where('id', $itemId)
            ->firstOrFail();

        $item->delete();

        return response()->json(['message' => 'Reconciliation item removed.']);
    }
}
