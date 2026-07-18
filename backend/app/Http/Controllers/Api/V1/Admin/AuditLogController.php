<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Landlord\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AuditLogController extends Controller
{
    #[OA\Get(path: "/admin/audit-logs", tags: ["Admin Audit"], summary: "List audit logs", responses: [new OA\Response(response: 200, description: "Audit logs listed")])]
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query()
            ->when($request->action_group, fn ($q, $g) => $q->where('action_group', $g))
            ->when($request->user_type, fn ($q, $t) => $q->where('user_type', $t))
            ->when($request->user_id, fn ($q, $u) => $q->where('user_id', $u))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('action', 'ilike', "%{$s}%")->orWhere('subject_description', 'ilike', "%{$s}%")))
            ->when($request->from, fn ($q, $f) => $q->where('created_at', '>=', $f))
            ->when($request->to, fn ($q, $t) => $q->where('created_at', '<=', $t));

        $logs = $query->orderByDesc('created_at')->paginate($request->per_page ?? 50);

        return response()->json($logs);
    }

    #[OA\Get(path: "/admin/audit-logs/export", tags: ["Admin Audit"], summary: "Export audit logs as CSV", responses: [new OA\Response(response: 200, description: "CSV download")])]
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $maxRows = config('audit.export_max_rows', 10000);

        $query = AuditLog::query()
            ->when($request->action_group, fn ($q, $g) => $q->where('action_group', $g))
            ->when($request->user_type, fn ($q, $t) => $q->where('user_type', $t))
            ->when($request->user_id, fn ($q, $u) => $q->where('user_id', $u))
            ->when($request->from, fn ($q, $f) => $q->where('created_at', '>=', $f))
            ->when($request->to, fn ($q, $t) => $q->where('created_at', '<=', $t))
            ->orderByDesc('created_at')
            ->limit($maxRows);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="admin-audit-log-' . date('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'ID', 'User ID', 'User Type', 'User Name', 'User Email',
                'Action', 'Action Group',
                'Subject Type', 'Subject ID', 'Subject Description',
                'IP Address', 'Method', 'URL', 'Timestamp',
            ]);

            $query->chunk(500, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->id,
                        $log->user_id,
                        $log->user_type,
                        $log->user_name,
                        $log->user_email,
                        $log->action,
                        $log->action_group,
                        $log->subject_type,
                        $log->subject_id,
                        $log->subject_description,
                        $log->ip_address,
                        $log->method,
                        $log->url,
                        $log->created_at?->toIso8601String(),
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }
}
