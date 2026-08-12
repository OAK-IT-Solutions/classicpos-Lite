<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\OperatingAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/operating-accounts")]
class OperatingAccountController extends BaseController
{
    protected string $modelClass = OperatingAccount::class;

    protected array $searchableFields = ['name', 'account_number', 'bank_name'];

    protected array $withRelations = ['account'];

    #[OA\Get(path: "/operating-accounts", tags: ["Accounting"], summary: "List operating accounts", responses: [new OA\Response(response: 200, description: "Paginated accounts")])]
    public function index(Request $request): JsonResponse
    {
        return parent::index($request);
    }

    #[OA\Post(path: "/operating-accounts", tags: ["Accounting"], summary: "Create an operating account", responses: [new OA\Response(response: 201, description: "Account created")])]
    public function store(Request $request): JsonResponse
    {
        return parent::store($request);
    }

    #[OA\Get(path: "/operating-accounts/{id}", tags: ["Accounting"], summary: "Get an operating account", responses: [new OA\Response(response: 200, description: "Account details")])]
    public function show(string $id): JsonResponse
    {
        return parent::show($id);
    }

    #[OA\Put(path: "/operating-accounts/{id}", tags: ["Accounting"], summary: "Update an operating account", responses: [new OA\Response(response: 200, description: "Account updated")])]
    public function update(Request $request, string $id): JsonResponse
    {
        return parent::update($request, $id);
    }

    #[OA\Delete(path: "/operating-accounts/{id}", tags: ["Accounting"], summary: "Delete an operating account", responses: [new OA\Response(response: 200, description: "Account deleted")])]
    public function destroy(string $id): JsonResponse
    {
        return parent::destroy($id);
    }

    protected function rules(Request $request, ?string $id = null): array
    {
        return [
            'account_id' => 'required|uuid|exists:chart_of_accounts,id',
            'name' => 'required|string|max:100',
            'type' => 'required|string|in:bank,petty_cash,cash,mobile_money',
            'account_number' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:100',
            'currency' => 'sometimes|string|size:3',
            'is_default' => 'sometimes|boolean',
            'opening_balance' => 'sometimes|numeric|min:0',
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
        $validated['branch_id'] = $request->user()->branch_id;
        $validated['current_balance'] = $validated['opening_balance'] ?? 0;
        $validated['is_system'] = false;
        $validated['is_active'] ??= true;

        return $validated;
    }

    protected function beforeUpdate(Request $request, Model $record, array $validated): array
    {
        if ($record->is_system) {
            unset($validated['name'], $validated['type'], $validated['account_id']);
        }

        return $validated;
    }

    protected function beforeDestroy(Model $record): void
    {
        if ($record->is_system) {
            abort(403, 'System operating accounts cannot be deleted.');
        }
    }
}
