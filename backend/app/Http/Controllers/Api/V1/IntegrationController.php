<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\IntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/integrations")]
class IntegrationController extends Controller
{
    public function __construct(
        private IntegrationService $integrationService,
    ) {}

    #[OA\Get(path: "/integrations", tags: ["Integrations"], summary: "List connected integrations", responses: [new OA\Response(response: 200, description: "Integration list")])]
    public function index(Request $request): JsonResponse
    {
        $branchId = $request->user()->branch_id;
        $integrations = $this->integrationService->getConnectedForBranch($branchId);

        return response()->json(['data' => $integrations]);
    }

    #[OA\Get(path: "/integrations/available", tags: ["Integrations"], summary: "List available integrations", responses: [new OA\Response(response: 200, description: "Available integrations")])]
    public function available(): JsonResponse
    {
        return response()->json(['data' => $this->integrationService->getAvailableIntegrations()]);
    }

    #[OA\Post(path: "/integrations", tags: ["Integrations"], summary: "Connect an integration", responses: [new OA\Response(response: 201, description: "Integration connected")])]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|max:50',
            'name' => 'required|string|max:100',
            'weaf_email' => 'required_if:type,efris|nullable|email',
            'weaf_password' => 'required_if:type,efris|nullable|string',
            'tin' => 'required_if:type,efris|nullable|string|max:20',
            'weaf_environment' => 'nullable|in:sandbox,production',
            'auto_fiscalize' => 'nullable|boolean',
            'fiscalize_receipts' => 'nullable|boolean',
        ]);

        $branchId = $request->user()->branch_id;

        try {
            $integration = $this->integrationService->connect($branchId, $validated['type'], $validated);
            return response()->json(['data' => $integration], 201);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => [
                    'code' => 'INTEGRATION_CONNECT_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    #[OA\Get(path: "/integrations/{id}", tags: ["Integrations"], summary: "Get an integration", responses: [new OA\Response(response: 200, description: "Integration details")])]
    public function show(string $id): JsonResponse
    {
        $integration = \App\Models\Integration::with('efrisConfig')->findOrFail($id);
        return response()->json(['data' => $integration]);
    }

    #[OA\Put(path: "/integrations/{id}", tags: ["Integrations"], summary: "Update an integration", responses: [new OA\Response(response: 200, description: "Integration updated")])]
    public function update(Request $request, string $id): JsonResponse
    {
        $integration = \App\Models\Integration::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'weaf_email' => 'sometimes|email',
            'weaf_password' => 'sometimes|string',
            'tin' => 'sometimes|string|max:20',
            'weaf_environment' => 'sometimes|in:sandbox,production',
            'auto_fiscalize' => 'sometimes|boolean',
            'fiscalize_receipts' => 'sometimes|boolean',
        ]);

        $integration->update(array_filter($validated, fn ($v) => !is_null($v)));

        if ($integration->type === 'efris' && $integration->efrisConfig) {
            $efrisFields = array_intersect_key($validated, array_flip([
                'weaf_email', 'weaf_password', 'tin', 'weaf_environment', 'auto_fiscalize', 'fiscalize_receipts',
            ]));
            if (!empty($efrisFields)) {
                $integration->efrisConfig->update(array_filter($efrisFields, fn ($v) => !is_null($v)));
            }
        }

        return response()->json(['data' => $integration->fresh('efrisConfig')]);
    }

    #[OA\Delete(path: "/integrations/{id}", tags: ["Integrations"], summary: "Disconnect an integration", responses: [new OA\Response(response: 200, description: "Integration disconnected")])]
    public function destroy(string $id): JsonResponse
    {
        $this->integrationService->disconnect($id);
        return response()->json(['message' => 'Integration disconnected successfully.']);
    }

    #[OA\Post(path: "/integrations/{id}/test", tags: ["Integrations"], summary: "Test integration connection", responses: [new OA\Response(response: 200, description: "Test result")])]
    public function testConnection(string $id): JsonResponse
    {
        $result = $this->integrationService->testConnection($id);

        if ($result['success']) {
            return response()->json($result);
        }

        return response()->json($result, 400);
    }

    #[OA\Post(path: "/integrations/{id}/sync", tags: ["Integrations"], summary: "Sync integration offline queue", responses: [new OA\Response(response: 200, description: "Sync results")])]
    public function sync(string $id): JsonResponse
    {
        $integration = \App\Models\Integration::with('efrisConfig')->findOrFail($id);

        if ($integration->type !== 'efris') {
            return response()->json(['error' => ['message' => 'Sync not supported for this integration type']], 400);
        }

        $branchId = $integration->branch_id;
        $results = $this->integrationService->processOfflineQueue($branchId);

        $integration->update(['last_sync_at' => now()]);

        return response()->json(['data' => $results]);
    }

    #[OA\Get(path: "/integrations/{id}/logs", tags: ["Integrations"], summary: "Get integration logs", responses: [new OA\Response(response: 200, description: "Paginated logs")])]
    public function logs(Request $request, string $id): JsonResponse
    {
        $integration = \App\Models\Integration::findOrFail($id);
        $branchId = $integration->branch_id;

        $logs = \App\Models\EfrisFiscalLog::where('branch_id', $branchId)
            ->with('sale')
            ->orderBy('created_at', 'desc')
            ->paginate(min((int) $request->get('per_page', 20), 100));

        return response()->json($logs);
    }
}
