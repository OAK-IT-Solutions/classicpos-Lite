<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/activity-logs")]
class ActivityLogController extends Controller
{
    #[OA\Get(path: "/activity-logs", tags: ["Audit"], summary: "List activity logs", responses: [new OA\Response(response: 200, description: "Paginated activity logs")])]
    public function index(Request $request): JsonResponse
    {
        $query = ActivityLog::query()
            ->select([
                'id',
                'user_id',
                'branch_id',
                'auditable_type',
                'auditable_id',
                'event',
                'old_values',
                'new_values',
                'url',
                'method',
                'status_code',
                'ip_address',
                'user_agent',
                'description',
                'created_at',
            ])
            ->with('user:id,name,email');

        // Filter by branch
        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        // Filter by event type
        if ($request->has('event')) {
            $query->where('event', $request->event);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by model type
        if ($request->has('auditable_type')) {
            $query->where('auditable_type', $request->auditable_type);
        }

        // Filter by date range
        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->from);
        }
        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->to . ' 23:59:59');
        }

        // Search description
        if ($request->has('search')) {
            $query->where('description', 'ILIKE', "%{$request->search}%");
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 50));

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    #[OA\Get(path: "/activity-logs/export", tags: ["Audit"], summary: "Export activity logs as CSV", responses: [new OA\Response(response: 200, description: "CSV download")])]
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $maxRows = config('audit.export_max_rows', 10000);

        $query = ActivityLog::query()
            ->select([
                'id',
                'user_id',
                'branch_id',
                'auditable_type',
                'auditable_id',
                'event',
                'description',
                'method',
                'status_code',
                'ip_address',
                'created_at',
            ])
            ->orderBy('created_at', 'desc')
            ->limit($maxRows);

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }
        if ($request->has('event')) {
            $query->where('event', $request->event);
        }
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->from);
        }
        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->to . ' 23:59:59');
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="audit-log-' . date('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'ID',
                'User ID',
                'Branch ID',
                'Model',
                'Model ID',
                'Event',
                'Description',
                'Method',
                'Status Code',
                'IP',
                'Timestamp',
            ]);

            $query->chunk(500, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->id,
                        $log->user_id,
                        $log->branch_id,
                        $log->auditable_type,
                        $log->auditable_id,
                        $log->event,
                        $log->description,
                        $log->method,
                        $log->status_code,
                        $log->ip_address,
                        $log->created_at->toIso8601String(),
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }
}
