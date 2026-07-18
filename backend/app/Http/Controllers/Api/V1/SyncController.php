<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BusinessProfile;
use App\Models\Sale;
use App\Models\Sync;
use App\Services\NetworkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/sync")]
class SyncController extends Controller
{
    public function __construct(
        protected NetworkService $networkService
    ) {}

    #[OA\Post(path: "/sync/start", tags: ["Sync"], summary: "Start sync process", responses: [new OA\Response(response: 200, description: "Sync status")])]
    public function start(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'last_sync' => 'nullable|date',
            'device_id' => 'nullable|string|max:64',
        ]);

        $pending = Sync::where('branch_id', $validated['branch_id'])
            ->where('status', 'pending')
            ->count();

        $pendingOfflineSales = Sale::where('branch_id', $validated['branch_id'])
            ->where('sync_status', 'pending')
            ->where('status', 'pending_sync')
            ->count();

        $connectivity = $this->networkService->checkConnectivity();

        return response()->json([
            'branch_id' => $validated['branch_id'],
            'online' => $connectivity['overall'],
            'pending_changes' => $pending,
            'pending_offline_sales' => $pendingOfflineSales,
            'last_sync' => $validated['last_sync'] ?? null,
            'latency_ms' => $connectivity['latency_ms'] ?? null,
        ]);
    }

    #[OA\Get(path: "/sync/status", tags: ["Sync"], summary: "Get sync status", responses: [new OA\Response(response: 200, description: "Sync status details")])]
    public function status(Request $request): JsonResponse
    {
        $status = $this->networkService->getSyncStatus();

        // Per-table breakdown
        $tables = ['sales' => 'sales', 'payments' => 'payments', 'syncs' => 'syncs'];
        $breakdown = [];
        foreach (['sales', 'payments', 'syncs'] as $table) {
            $breakdown[$table] = [
                'pending' => $this->safeCount($table, 'pending'),
                'synced' => $this->safeCount($table, 'synced'),
                'failed' => $this->safeCount($table, 'failed'),
            ];
        }

        $user = $request->user();
        $branchId = $user?->branch_id ?? $user?->branch?->id ?? null;

        $syncMode = 'auto';
        if ($branchId) {
            $profile = BusinessProfile::where('branch_id', $branchId)->first();
            $syncMode = $profile?->settings['sync_mode'] ?? 'auto';
        }

        $pendingOfflineSales = 0;
        if ($branchId) {
            $pendingOfflineSales = Sale::where('branch_id', $branchId)
                ->where('status', 'pending_sync')
                ->count();
        }

        return response()->json([
            'data' => array_merge($status, [
                'sync_mode' => $syncMode,
                'tables' => $breakdown,
                'pending_offline_sales' => $pendingOfflineSales,
            ]),
        ]);
    }

    protected function safeCount(string $table, string $status): int
    {
        try {
            if (!\Schema::hasTable($table)) return 0;
            if ($table === 'syncs') {
                return \DB::table('syncs')->where('status', $status)->count();
            }
            if ($table === 'sales') {
                if ($status === 'pending') {
                    return \DB::table('sales')->where('sync_status', 'pending')->count();
                }
                if ($status === 'synced') {
                    return \DB::table('sales')->where('sync_status', 'synced')->count();
                }
                return \DB::table('sales')->where('sync_status', $status)->count();
            }
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
}
