<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ChartOfAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/chart-of-accounts")]
class ChartOfAccountController extends BaseController
{
    protected string $modelClass = ChartOfAccount::class;

    protected array $searchableFields = ['code', 'name', 'description'];

    protected array $withRelations = [];

    #[OA\Get(path: "/chart-of-accounts", tags: ["Accounting"], summary: "List chart of accounts", responses: [new OA\Response(response: 200, description: "Paginated accounts")])]
    public function index(Request $request): JsonResponse
    {
        return parent::index($request);
    }

    #[OA\Post(path: "/chart-of-accounts", tags: ["Accounting"], summary: "Create a chart of account", responses: [new OA\Response(response: 201, description: "Account created")])]
    public function store(Request $request): JsonResponse
    {
        return parent::store($request);
    }

    #[OA\Get(path: "/chart-of-accounts/{id}", tags: ["Accounting"], summary: "Get a chart of account", responses: [new OA\Response(response: 200, description: "Account details")])]
    public function show(string $id): JsonResponse
    {
        return parent::show($id);
    }

    #[OA\Put(path: "/chart-of-accounts/{id}", tags: ["Accounting"], summary: "Update a chart of account", responses: [new OA\Response(response: 200, description: "Account updated")])]
    public function update(Request $request, string $id): JsonResponse
    {
        return parent::update($request, $id);
    }

    #[OA\Delete(path: "/chart-of-accounts/{id}", tags: ["Accounting"], summary: "Delete a chart of account", responses: [new OA\Response(response: 200, description: "Account deleted")])]
    public function destroy(string $id): JsonResponse
    {
        return parent::destroy($id);
    }

    protected function rules(Request $request, ?string $id = null): array
    {
        return [
            'branch_id' => 'sometimes|required|uuid|exists:branches,id',
            'code' => 'required|string|max:20',
            'name' => 'required|string|max:200',
            'type' => 'required|string|in:asset,liability,equity,revenue,expense',
            'group' => 'nullable|string|max:50',
            'normal_balance' => 'required|string|in:debit,credit',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ];
    }

    protected function additionalQuery(Request $request, $query): void
    {
        $branchIds = $request->user()->branches->pluck('id')->push($request->user()->branch_id)->unique();
        $query->whereIn('branch_id', $branchIds);
    }

    protected function beforeStore(Request $request, array $validated): array
    {
        $validated['branch_id'] ??= $request->user()->branch_id;
        $validated['is_system'] = false;
        $validated['is_active'] ??= true;

        return $validated;
    }

    protected function beforeUpdate(Request $request, Model $record, array $validated): array
    {
        if ($record->is_system) {
            unset($validated['code'], $validated['name'], $validated['type'], $validated['normal_balance']);
        }

        return $validated;
    }

    protected function beforeDestroy(Model $record): void
    {
        if ($record->is_system) {
            abort(403, 'System accounts cannot be deleted.');
        }
    }
}
