<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/devices")]
class DeviceController extends BaseController
{
    protected string $modelClass = Device::class;

    protected array $withRelations = ['branch'];

    protected function rules(Request $request, ?string $id = null): array
    {
        if ($id) {
            return [
                'name' => 'sometimes|required|string|max:100',
                'type' => 'sometimes|required|string|in:edge_node,pos_terminal,tablet,phone',
                'status' => 'sometimes|required|string|in:pending,active,inactive,decommissioned',
                'description' => 'nullable|string|max:500',
                'firmware_version' => 'nullable|string|max:50',
                'ip_address' => 'nullable|string|max:45',
                'mac_address' => 'nullable|string|max:17',
                'os' => 'nullable|string|max:100',
                'capabilities' => 'nullable|json',
                'config' => 'nullable|json',
            ];
        }

        return [
            'branch_id' => 'required|uuid|exists:branches,id',
            'name' => 'required|string|max:100',
            'device_id' => 'required|string|max:255|unique:devices,device_id',
            'type' => 'required|string|in:edge_node,pos_terminal,tablet,phone',
            'description' => 'nullable|string|max:500',
            'os' => 'nullable|string|max:100',
            'ip_address' => 'nullable|string|max:45',
            'mac_address' => 'nullable|string|max:17',
            'capabilities' => 'nullable|json',
            'config' => 'nullable|json',
        ];
    }

    protected function beforeStore(Request $request, array $validated): array
    {
        $validated['status'] = 'pending';
        $validated['enrollment_token'] = Str::random(64);

        return $validated;
    }

    protected function additionalQuery(Request $request, $query): void
    {
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
    }

    #[OA\Get(path: "/devices", tags: ["Devices"], summary: "List all devices", responses: [new OA\Response(response: 200, description: "Device list")])]
    public function index(Request $request): JsonResponse
    {
        $query = $this->indexQuery($request)->orderByDesc('created_at');
        $devices = $query->get();

        return response()->json(['data' => $devices]);
    }

    #[OA\Post(path: "/devices/enroll", tags: ["Devices"], summary: "Enroll a device", responses: [new OA\Response(response: 200, description: "Enrolled device")])]
    public function enroll(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|string|max:255',
            'enrollment_token' => 'required|string|max:255',
            'ip_address' => 'nullable|string|max:45',
            'mac_address' => 'nullable|string|max:17',
            'firmware_version' => 'nullable|string|max:50',
            'os' => 'nullable|string|max:100',
        ]);

        $device = Device::where('device_id', $validated['device_id'])
            ->where('enrollment_token', $validated['enrollment_token'])
            ->whereIn('status', ['pending', 'inactive'])
            ->first();

        if (!$device) {
            return response()->json([
                'error' => [
                    'code' => 'ERR_ENROLLMENT_FAILED',
                    'message' => 'Invalid device ID or enrollment token.',
                    'details' => [],
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 400);
        }

        $device->update([
            'status' => 'active',
            'ip_address' => $validated['ip_address'] ?? $device->ip_address,
            'mac_address' => $validated['mac_address'] ?? $device->mac_address,
            'firmware_version' => $validated['firmware_version'] ?? $device->firmware_version,
            'os' => $validated['os'] ?? $device->os,
            'enrolled_at' => now(),
            'last_seen_at' => now(),
            'enrollment_token' => null,
        ]);

        return response()->json(['data' => $device]);
    }

    #[OA\Post(path: "/devices/heartbeat", tags: ["Devices"], summary: "Device heartbeat", responses: [new OA\Response(response: 200, description: "Heartbeat acknowledged")])]
    public function heartbeat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|string|max:255',
            'status' => 'nullable|string|in:active,inactive,error',
        ]);

        $device = Device::where('device_id', $validated['device_id'])->first();

        if (!$device) {
            return response()->json([
                'error' => [
                    'code' => 'ERR_DEVICE_NOT_FOUND',
                    'message' => 'Device not found.',
                    'details' => [],
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 404);
        }

        $device->update([
            'last_seen_at' => now(),
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json(['message' => 'Heartbeat received.']);
    }
}
