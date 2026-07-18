<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Landlord\AuditLog;
use App\Models\Landlord\SupportTicket;
use App\Models\Landlord\TicketMessage;
use App\Models\User;
use App\Notifications\TicketCreated;
use App\Notifications\TicketReply;
use App\Notifications\TicketResolved;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/tickets")]
class TicketController extends Controller
{
    #[OA\Get(path: "/tickets", tags: ["Support"], summary: "List support tickets", responses: [new OA\Response(response: 200, description: "Paginated tickets")])]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $request->attributes->get('tenant')?->id;

        $query = SupportTicket::where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->with('latestMessage');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($priority = $request->input('priority')) {
            $query->where('priority', $priority);
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'ilike', "%{$search}%")
                  ->orWhere('ticket_number', 'ilike', "%{$search}%")
                  ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        $tickets = $query->orderByDesc('created_at')
            ->paginate($request->input('per_page', 15));

        return response()->json($tickets);
    }

    #[OA\Post(path: "/tickets", tags: ["Support"], summary: "Create a support ticket", responses: [new OA\Response(response: 201, description: "Ticket created")])]
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $request->attributes->get('tenant');

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'category' => 'required|in:billing,technical,feature_request,general,bug_report,account',
            'priority' => 'required|in:low,medium,high,urgent',
            'attachments.*' => 'nullable|file|max:10240|mimes:jpeg,png,jpg,gif,webp,pdf,doc,docx,xls,xlsx,txt,csv',
        ]);

        $ticketNumber = 'TKT-' . strtoupper(Str::random(8));

        $ticket = SupportTicket::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'ticket_number' => $ticketNumber,
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'status' => 'open',
            'priority' => $validated['priority'],
            'category' => $validated['category'],
            'message_count' => 1,
            'unread_count' => 0,
        ]);

        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('tickets', 'public');
                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => '/storage/' . $path,
                    'size' => $file->getSize(),
                    'mime' => $file->getMimeType(),
                ];
            }
        }

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_type' => 'tenant_user',
            'sender_id' => $user->id,
            'sender_name' => $user->name,
            'sender_email' => $user->email,
            'message' => $validated['description'],
            'attachments' => $attachments ?: null,
        ]);

        // Notify admins
        $admins = User::whereHas('roles', fn($q) => $q->where('name', 'super_admin'))->get();
        foreach ($admins as $admin) {
            $admin->notify(new TicketCreated($ticket));
        }

        AuditLog::log('ticket.create', 'ticket', 'SupportTicket', $ticket->id, "#{$ticket->ticket_number} {$ticket->subject}");

        return response()->json([
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'category' => $ticket->category,
            'created_at' => $ticket->created_at->toIso8601String(),
        ], 201);
    }

    #[OA\Get(path: "/tickets/{id}", tags: ["Support"], summary: "Get a support ticket with messages", responses: [new OA\Response(response: 200, description: "Ticket details")])]
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $tenantId = $request->attributes->get('tenant')?->id;

        $ticket = SupportTicket::where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->with(['messages' => fn($q) => $q->orderBy('created_at')])
            ->findOrFail($id);

        // Mark messages from admin as read
        TicketMessage::where('ticket_id', $ticket->id)
            ->where('sender_type', 'admin')
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        $ticket->update(['unread_count' => 0]);

        return response()->json([
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'status' => $ticket->status,
            'status_label' => $ticket->statusLabel(),
            'priority' => $ticket->priority,
            'priority_label' => $ticket->priorityLabel(),
            'category' => $ticket->category,
            'message_count' => $ticket->message_count,
            'created_at' => $ticket->created_at->toIso8601String(),
            'sla_response_hours' => $ticket->sla_response_hours,
            'sla_resolve_hours' => $ticket->sla_resolve_hours,
            'first_response_at' => $ticket->first_response_at?->toIso8601String(),
            'resolved_at' => $ticket->resolved_at?->toIso8601String(),
            'closed_at' => $ticket->closed_at?->toIso8601String(),
            'messages' => $ticket->messages->map(fn($m) => [
                'id' => $m->id,
                'sender_type' => $m->sender_type,
                'sender_name' => $m->sender_name,
                'message' => $m->message,
                'is_read' => $m->is_read,
                'created_at' => $m->created_at->toIso8601String(),
                'attachments' => $m->attachments,
            ]),
        ]);
    }

    #[OA\Post(path: "/tickets/{id}/reply", tags: ["Support"], summary: "Reply to a support ticket", responses: [new OA\Response(response: 201, description: "Reply sent")])]
    public function reply(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $tenantId = $request->attributes->get('tenant')?->id;

        $ticket = SupportTicket::where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->where('status', '!=', 'closed')
            ->findOrFail($id);

        $validated = $request->validate([
            'message' => 'required|string|max:5000',
            'attachments.*' => 'nullable|file|max:10240|mimes:jpeg,png,jpg,gif,webp,pdf,doc,docx,xls,xlsx,txt,csv',
        ]);

        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('tickets', 'public');
                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => '/storage/' . $path,
                    'size' => $file->getSize(),
                    'mime' => $file->getMimeType(),
                ];
            }
        }

        $message = TicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_type' => 'tenant_user',
            'sender_id' => $user->id,
            'sender_name' => $user->name,
            'sender_email' => $user->email,
            'message' => $validated['message'],
            'attachments' => $attachments ?: null,
        ]);

        $ticket->update([
            'status' => 'waiting_reply',
            'message_count' => $ticket->message_count + 1,
        ]);

        // Notify admins of reply
        $admins = User::whereHas('roles', fn($q) => $q->where('name', 'super_admin'))->get();
        foreach ($admins as $admin) {
            $admin->notify(new TicketReply($ticket, $message));
        }

        AuditLog::log('ticket.reply', 'ticket', 'SupportTicket', $ticket->id, "#{$ticket->ticket_number} tenant replied");

        return response()->json([
            'id' => $message->id,
            'sender_type' => $message->sender_type,
            'sender_name' => $message->sender_name,
            'message' => $message->message,
            'created_at' => $message->created_at->toIso8601String(),
        ], 201);
    }

    #[OA\Post(path: "/tickets/{id}/close", tags: ["Support"], summary: "Close a support ticket", responses: [new OA\Response(response: 200, description: "Ticket closed")])]
    public function close(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $tenantId = $request->attributes->get('tenant')?->id;

        $ticket = SupportTicket::where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->whereIn('status', ['open', 'in_progress', 'waiting_reply'])
            ->findOrFail($id);

        $ticket->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        // System message
        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_type' => 'system',
            'sender_name' => 'System',
            'message' => 'Ticket closed by ' . $user->name,
        ]);

        AuditLog::log('ticket.close', 'ticket', 'SupportTicket', $ticket->id, "#{$ticket->ticket_number} closed by tenant");

        return response()->json(['success' => true, 'status' => 'closed']);
    }

    #[OA\Post(path: "/tickets/{id}/reopen", tags: ["Support"], summary: "Reopen a support ticket", responses: [new OA\Response(response: 200, description: "Ticket reopened")])]
    public function reopen(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $tenantId = $request->attributes->get('tenant')?->id;

        $ticket = SupportTicket::where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->where('status', 'closed')
            ->findOrFail($id);

        $ticket->update([
            'status' => 'open',
            'closed_at' => null,
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_type' => 'system',
            'sender_name' => 'System',
            'message' => 'Ticket reopened by ' . $user->name,
        ]);

        AuditLog::log('ticket.reopen', 'ticket', 'SupportTicket', $ticket->id, "#{$ticket->ticket_number} reopened by tenant");

        return response()->json(['success' => true, 'status' => 'open']);
    }
}
