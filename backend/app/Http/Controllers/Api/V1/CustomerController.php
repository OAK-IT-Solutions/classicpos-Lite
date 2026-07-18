<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Customer;
use App\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/customers")]
class CustomerController extends BaseController
{
    protected string $modelClass = Customer::class;

    protected array $searchableFields = ['name', 'phone', 'email'];

    public function __construct(
        protected LoyaltyService $loyaltyService,
    ) {}

    protected function rules(Request $request, ?string $id = null): array
    {
        $uniquePhone = 'unique:customers,phone' . ($id ? ',' . $id : '');

        return [
            'phone' => ($id ? 'sometimes|' : '') . 'required|string|' . $uniquePhone,
            'email' => 'nullable|email',
            'name' => ($id ? 'sometimes|' : '') . 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'member_level' => 'nullable|string|in:bronze,silver,gold,platinum',
            'loyalty_points' => 'nullable|integer|min:0',
            'branch_id' => 'nullable|uuid|exists:branches,id',
        ];
    }

    protected function indexQuery(Request $request)
    {
        $query = parent::indexQuery($request);

        $branchId = $request->user()->branch_id;
        if ($branchId) {
            $query->where('customers.branch_id', $branchId);
        }

        return $query;
    }

    protected function beforeStore(Request $request, array $validated): array
    {
        if (empty($validated['branch_id'])) {
            $validated['branch_id'] = $request->user()->branch_id;
        }

        return $validated;
    }

    protected function afterStore(\Illuminate\Database\Eloquent\Model $record): void
    {
        $this->loyaltyService->awardSignupBonus($record->id);
    }

    public function show(string $id): JsonResponse
    {
        $customer = Customer::with('sales')->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'location' => $customer->location,
                'loyalty_points' => $customer->loyalty_points,
                'member_level' => $customer->member_level,
                'branch_id' => $customer->branch_id,
                'total_spend' => $customer->total_spend,
                'total_visits' => $customer->total_visits,
                'avg_order_value' => $customer->avg_order_value,
                'last_purchase_date' => $customer->last_purchase_date?->toIso8601String(),
                'created_at' => $customer->created_at?->toIso8601String(),
                'sales' => $customer->sales()
                    ->latest('created_at')
                    ->limit(50)
                    ->get()
                    ->map(fn($sale) => [
                        'id' => $sale->id,
                        'invoice_number' => $sale->invoice_number,
                        'total_amount' => $sale->total_amount,
                        'payment_method' => $sale->payment_method,
                        'status' => $sale->status,
                        'created_at' => $sale->created_at?->toIso8601String(),
                    ]),
            ],
        ]);
    }

    public function stats(string $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        return response()->json([
            'data' => [
                'total_spend' => $customer->total_spend,
                'total_visits' => $customer->total_visits,
                'avg_order_value' => $customer->avg_order_value,
                'last_purchase_date' => $customer->last_purchase_date?->toIso8601String(),
                'loyalty_points' => $customer->loyalty_points,
                'member_level' => $customer->member_level,
            ],
        ]);
    }

    public function restore(string $id): JsonResponse
    {
        $customer = Customer::withTrashed()->findOrFail($id);
        $customer->restore();

        return response()->json([
            'message' => 'Customer restored successfully.',
            'data' => [
                'id' => $customer->id,
                'name' => $customer->name,
            ],
        ]);
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = Customer::onlyTrashed()
            ->orderByDesc('deleted_at');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('phone', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $customers = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'data' => $customers->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'email' => $c->email,
                'deleted_at' => $c->deleted_at?->toIso8601String(),
            ]),
            'current_page' => $customers->currentPage(),
            'last_page' => $customers->lastPage(),
            'total' => $customers->total(),
        ]);
    }
}
