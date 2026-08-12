<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/journal-entries")]
class JournalEntryController extends BaseController
{
    protected string $modelClass = JournalEntry::class;

    protected array $searchableFields = ['entry_number', 'description'];

    protected array $withRelations = ['lines', 'lines.account', 'createdBy'];

    public function __construct(
        protected AccountingService $accountingService,
    ) {}

    protected function rules(Request $request, ?string $id = null): array
    {
        return [
            'entry_date' => 'required|date',
            'description' => 'required|string|max:1000',
            'lines' => 'required|array|min:1',
            'lines.*.account_code' => 'required_without:lines.*.account_id|string|max:20',
            'lines.*.account_id' => 'required_without:lines.*.account_code|uuid|exists:chart_of_accounts,id',
            'lines.*.debit' => 'numeric|min:0',
            'lines.*.credit' => 'numeric|min:0',
            'lines.*.description' => 'nullable|string|max:500',
            'reference_type' => 'nullable|string|max:50',
            'reference_id' => 'nullable|uuid',
        ];
    }

    #[OA\Get(path: "/journal-entries", tags: ["Accounting"], summary: "List journal entries", responses: [new OA\Response(response: 200, description: "Paginated journal entries")])]
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $branchIds = $request->user()->branches->pluck('id')->push($request->user()->branch_id)->unique();

        $query = JournalEntry::with($this->withRelations)
            ->whereIn('branch_id', $branchIds);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                foreach ($this->searchableFields as $field) {
                    $q->orWhereRaw('LOWER(' . $field . ') LIKE ?', ['%' . strtolower($search) . '%']);
                }
            });
        }

        if ($dateFrom = $request->get('date_from')) {
            $query->where('entry_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->get('date_to')) {
            $query->where('entry_date', '<=', $dateTo);
        }

        if ($referenceType = $request->get('reference_type')) {
            $query->where('reference_type', $referenceType);
        }

        $query->orderBy('entry_date', 'desc')->orderBy('created_at', 'desc');

        $perPage = min((int) ($request->get('per_page', 15)), 100);
        $items = $query->paginate($perPage);

        return response()->json($items);
    }

    #[OA\Post(path: "/journal-entries", tags: ["Accounting"], summary: "Create a journal entry", responses: [new OA\Response(response: 201, description: "Journal entry created")])]
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate($this->rules($request));

        $entry = $this->accountingService->createJournalEntry(
            branchId: $request->user()->branch_id,
            entryDate: $validated['entry_date'],
            description: $validated['description'],
            lines: $validated['lines'],
            referenceType: $validated['reference_type'] ?? null,
            referenceId: $validated['reference_id'] ?? null,
            createdBy: $request->user()->id,
        );

        return response()->json(['data' => $entry], 201);
    }

    #[OA\Get(path: "/journal-entries/{id}", tags: ["Accounting"], summary: "Get a journal entry", responses: [new OA\Response(response: 200, description: "Journal entry details")])]
    public function show(string $id): \Illuminate\Http\JsonResponse
    {
        $entry = JournalEntry::with($this->withRelations)->findOrFail($id);

        return response()->json(['data' => $entry]);
    }
}
