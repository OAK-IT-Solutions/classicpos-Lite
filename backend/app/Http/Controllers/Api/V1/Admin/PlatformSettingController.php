<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Landlord\PlatformSetting;
use App\Models\Landlord\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PlatformSettingController extends Controller
{
    #[OA\Get(path: "/admin/settings", tags: ["Admin Settings"], summary: "List platform settings", responses: [new OA\Response(response: 200, description: "Settings listed")])]
    public function index(Request $request): JsonResponse
    {
        $query = PlatformSetting::query();

        if ($request->group) {
            $query->where('group', $request->group);
        }

        $settings = $query->orderBy('group')->orderBy('key')->get();

        // Group by category
        $grouped = $settings->groupBy('group')->map(function ($items) {
            return $items->map(fn ($item) => [
                'key' => $item->key,
                'value' => $item->getValue(),
                'type' => $item->type,
                'description' => $item->description,
            ]);
        });

        return response()->json($grouped);
    }

    #[OA\Put(path: "/admin/settings", tags: ["Admin Settings"], summary: "Update platform settings", responses: [new OA\Response(response: 200, description: "Settings updated")])]
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'required',
        ]);

        $old = PlatformSetting::whereIn('key', array_column($data['settings'], 'key'))
            ->pluck('value', 'key')
            ->toArray();

        foreach ($data['settings'] as $setting) {
            PlatformSetting::set($setting['key'], $setting['value']);
        }

        AuditLog::log('settings.update', 'system', 'PlatformSetting', null, 'Platform settings updated', $old, $data['settings']);

        return response()->json(['message' => 'Settings updated']);
    }
}
