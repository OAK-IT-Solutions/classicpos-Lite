<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Landlord\SubscriptionFeature;
use App\Models\Landlord\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class FeatureController extends Controller
{
    #[OA\Get(path: "/admin/features", tags: ["Admin Features"], summary: "List subscription features", responses: [new OA\Response(response: 200, description: "Features listed")])]
    public function index(): JsonResponse
    {
        $features = SubscriptionFeature::withCount('plans')->orderBy('sort_order')->get();
        return response()->json($features);
    }

    #[OA\Post(path: "/admin/features", tags: ["Admin Features"], summary: "Create feature", responses: [new OA\Response(response: 201, description: "Feature created")])]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:subscription_features,slug',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:100',
            'group_name' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $feature = SubscriptionFeature::create($data);

        AuditLog::log('feature.create', 'billing', 'SubscriptionFeature', $feature->id, $feature->name);

        return response()->json($feature, 201);
    }

    #[OA\Get(path: "/admin/features/{feature}", tags: ["Admin Features"], summary: "Get feature", responses: [new OA\Response(response: 200, description: "Feature returned")])]
    public function show(SubscriptionFeature $feature): JsonResponse
    {
        $feature->loadCount('plans');
        return response()->json($feature);
    }

    #[OA\Put(path: "/admin/features/{feature}", tags: ["Admin Features"], summary: "Update feature", responses: [new OA\Response(response: 200, description: "Feature updated")])]
    public function update(Request $request, SubscriptionFeature $feature): JsonResponse
    {
        $old = $feature->toArray();

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:100',
            'group_name' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        if ($request->has('slug') && $request->slug !== $feature->slug) {
            $request->validate(['slug' => 'required|string|max:100|unique:subscription_features,slug,' . $feature->id]);
            $data['slug'] = $request->slug;
        }

        $feature->update($data);

        AuditLog::log('feature.update', 'billing', 'SubscriptionFeature', $feature->id, $feature->name, $old, $feature->fresh()->toArray());

        return response()->json($feature->fresh());
    }

    #[OA\Delete(path: "/admin/features/{feature}", tags: ["Admin Features"], summary: "Delete feature", responses: [new OA\Response(response: 200, description: "Feature deleted")])]
    public function destroy(SubscriptionFeature $feature): JsonResponse
    {
        if ($feature->plans()->count() > 0) {
            return response()->json(['error' => 'Cannot delete a feature that is assigned to plans'], 400);
        }

        AuditLog::log('feature.delete', 'billing', 'SubscriptionFeature', $feature->id, $feature->name);
        $feature->delete();

        return response()->json(['message' => 'Feature deleted']);
    }
}
