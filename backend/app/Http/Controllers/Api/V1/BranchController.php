<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Branch;
use App\Services\SubscriptionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/branches")]
class BranchController extends BaseController
{
    protected string $modelClass = Branch::class;

    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {}

    protected function rules(Request $request, ?string $id = null): array
    {
        return [
            'name' => ($id ? 'sometimes|' : '') . 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'timezone' => 'nullable|string|max:50',
            'edge_device_id' => 'nullable|string|max:255',
        ];
    }

    protected function beforeStore(Request $request, array $validated): array
    {
        $userBranch = Branch::findOrFail($request->user()->branch_id);

        if (!$this->subscriptionService->canCreateBranch($userBranch)) {
            abort(403, json_encode([
                'error' => [
                    'code' => 'ERR_PLAN_LIMIT_REACHED',
                    'message' => 'Your current plan does not allow creating more branches. Upgrade to add more.',
                    'details' => [],
                    'timestamp' => now()->toIso8601String(),
                ],
            ]));
        }

        return $validated;
    }

    protected function beforeDestroy(Model $record): void
    {
        if ($record->sales()->whereIn('status', ['pending_sync', 'completed'])->exists()) {
            abort(409, json_encode([
                'error' => [
                    'code' => 'ERR_BRANCH_HAS_ACTIVE_SALES',
                    'message' => 'Cannot delete branch with active sales.',
                    'details' => [],
                    'timestamp' => now()->toIso8601String(),
                ],
            ]));
        }
    }

    #[OA\Get(path: "/branches/{id}", tags: ["Branches"], summary: "Get branch details", responses: [new OA\Response(response: 200, description: "Branch data")])]
    public function show(string $id): JsonResponse
    {
        $record = Branch::withCount(['inventory', 'warehouses'])->findOrFail($id);

        return response()->json(['data' => $record]);
    }
}
