<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Landlord\Tenant;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\PaymentTransaction;
use App\Models\Landlord\AuditLog;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Rules\ComplexPassword;
use OpenApi\Attributes as OA;

class TenantController extends Controller
{
    public function __construct(
        private TenantService $tenantService,
    ) {}

    #[OA\Get(path: "/admin/tenants", tags: ["Admin Tenants"], summary: "List tenants", responses: [new OA\Response(response: 200, description: "Tenants listed")])]
    public function index(Request $request): JsonResponse
    {
        $query = Tenant::with('subscription.plan')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'ilike', "%{$s}%")->orWhere('slug', 'ilike', "%{$s}%")))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s));

        $tenants = $query->orderByDesc('created_at')->paginate($request->per_page ?? 20);

        return response()->json($tenants);
    }

    #[OA\Post(path: "/admin/tenants", tags: ["Admin Tenants"], summary: "Create tenant", responses: [new OA\Response(response: 201, description: "Tenant created")])]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:100',
            'email' => 'required|email',
            'password' => ['required', 'string', new ComplexPassword, 'confirmed'],
            'business_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'plan' => 'nullable|string|max:100',
        ]);

        // Validate plan exists in landlord DB
        if (!empty($data['plan'])) {
            $planExists = \App\Models\Landlord\SubscriptionPlan::where('slug', $data['plan'])->exists();
            if (!$planExists) {
                return response()->json([
                    'error' => [
                        'code' => 'ERR_VALIDATION',
                        'message' => 'The selected plan is invalid.',
                        'details' => ['plan' => ['The selected plan does not exist.']],
                        'timestamp' => now()->toIso8601String(),
                    ],
                ], 422);
            }
        }

        $data['slug'] = $data['slug'] ?? $this->tenantService->generateSlug($data['name']);

        $tenant = $this->tenantService->createTenant($data);

        AuditLog::log('tenant.create', 'tenant', 'Tenant', $tenant->id, $tenant->name);

        return response()->json($tenant->load('subscription.plan'), 201);
    }

    #[OA\Get(path: "/admin/tenants/{tenant}", tags: ["Admin Tenants"], summary: "Get tenant", responses: [new OA\Response(response: 200, description: "Tenant returned")])]
    public function show(Tenant $tenant): JsonResponse
    {
        $tenant->load('subscription.plan', 'paymentTransactions', 'supportTickets');

        // Add computed stats
        $tenant->loadCount(['supportTickets', 'paymentTransactions' => fn ($q) => $q->where('status', 'success')]);

        return response()->json($tenant);
    }

    #[OA\Put(path: "/admin/tenants/{tenant}", tags: ["Admin Tenants"], summary: "Update tenant", responses: [new OA\Response(response: 200, description: "Tenant updated")])]
    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'business_name' => 'nullable|string|max:255',
            'business_email' => 'nullable|email',
            'business_phone' => 'nullable|string|max:50',
            'domain' => 'nullable|string|max:255',
        ]);

        $old = $tenant->toArray();
        $tenant->update($data);

        AuditLog::log('tenant.update', 'tenant', 'Tenant', $tenant->id, $tenant->name, $old, $tenant->fresh()->toArray());

        return response()->json($tenant->fresh());
    }

    #[OA\Delete(path: "/admin/tenants/{tenant}", tags: ["Admin Tenants"], summary: "Delete tenant", responses: [new OA\Response(response: 200, description: "Tenant deleted")])]
    public function destroy(Tenant $tenant): JsonResponse
    {
        $this->tenantService->deleteTenant($tenant->id);
        return response()->json(['message' => 'Tenant deleted']);
    }

    #[OA\Post(path: "/admin/tenants/{tenant}/suspend", tags: ["Admin Tenants"], summary: "Suspend tenant", responses: [new OA\Response(response: 200, description: "Tenant suspended")])]
    public function suspend(Tenant $tenant): JsonResponse
    {
        $request = request();
        $reason = $request->input('reason');

        $tenant = $this->tenantService->suspendTenant($tenant->id, $reason);
        return response()->json($tenant);
    }

    #[OA\Post(path: "/admin/tenants/{tenant}/activate", tags: ["Admin Tenants"], summary: "Activate tenant", responses: [new OA\Response(response: 200, description: "Tenant activated")])]
    public function activate(Tenant $tenant): JsonResponse
    {
        $tenant = $this->tenantService->activateTenant($tenant->id);
        return response()->json($tenant);
    }

    #[OA\Post(path: "/admin/tenants/{tenant}/cancel", tags: ["Admin Tenants"], summary: "Cancel tenant", responses: [new OA\Response(response: 200, description: "Tenant cancelled")])]
    public function cancel(Tenant $tenant): JsonResponse
    {
        $reason = request()->input('reason');
        $tenant = $this->tenantService->cancelTenant($tenant->id, $reason);
        return response()->json($tenant);
    }

    #[OA\Post(path: "/admin/tenants/{tenant}/impersonate", tags: ["Admin Tenants"], summary: "Impersonate tenant", responses: [new OA\Response(response: 200, description: "Impersonation token returned")])]
    public function impersonate(Tenant $tenant): JsonResponse
    {
        // Generate a temporary impersonation token
        // This logs in as the tenant's admin user
        $adminUser = \App\Models\User::where('email', $tenant->business_email)
            ->whereHas('roles', fn ($q) => $q->where('name', 'admin'))
            ->first();

        if (!$adminUser) {
            return response()->json(['error' => 'No admin user found for this tenant'], 404);
        }

        $token = $adminUser->createToken('impersonation-' . now()->timestamp)->plainTextToken;

        AuditLog::log('tenant.impersonate', 'tenant', 'Tenant', $tenant->id, $tenant->name);

        return response()->json([
            'token' => $token,
            'user' => $adminUser,
            'tenant' => $tenant,
        ]);
    }
}
