<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Landlord\SupportTicket;
use App\Models\Landlord\TicketMessage;
use App\Models\Landlord\AuditLog;
use App\Models\User;
use App\Notifications\TicketResolved;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class AdminTicketController extends Controller
{
    #[OA\Get(path: "/admin/tickets", tags: ["Admin Tickets"], summary: "List support tickets", responses: [new OA\Response(response: 200, description: "Tickets listed")])]
    public function index(Request $request): JsonResponse
    {
        $query = SupportTicket::with('tenant')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->priority, fn ($q, $p) => $q->where('priority', $p))
            ->when($request->category, fn ($q, $c) => $q->where('category', $c))
            ->when($request->assigned_to, fn ($q, $a) => $q->where('assigned_to', $a))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('subject', 'ilike', "%{$s}%")->orWhere('ticket_number', 'ilike', "%{$s}%")));

        $tickets = $query->orderByDesc('created_at')->paginate($request->per_page ?? 20);

        return response()->json($tickets);
    }

    #[OA\Get(path: "/admin/tickets/{ticket}", tags: ["Admin Tickets"], summary: "Get support ticket", responses: [new OA\Response(response: 200, description: "Ticket returned")])]
    public function show(SupportTicket $ticket): JsonResponse
    {
        $ticket->load('tenant', 'messages');

        return response()->json($ticket);
    }

    #[OA\Post(path: "/admin/tickets/{ticket}/assign", tags: ["Admin Tickets"], summary: "Assign ticket", responses: [new OA\Response(response: 200, description: "Ticket assigned")])]
    public function assign(Request $request, SupportTicket $ticket): JsonResponse
    {
        $data = $request->validate([
            'assigned_to' => 'required|uuid',
        ]);

        $ticket->update([
            'assigned_to' => $data['assigned_to'],
            'assigned_at' => now(),
            'status' => 'in_progress',
        ]);

        // Add system message
        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_type' => 'system',
            'message' => "Ticket assigned to support agent.",
        ]);

        AuditLog::log('ticket.assign', 'ticket', 'SupportTicket', $ticket->id, "#{$ticket->ticket_number} assigned");

        return response()->json($ticket->fresh()->load('tenant'));
    }

    #[OA\Post(path: "/admin/tickets/{ticket}/reply", tags: ["Admin Tickets"], summary: "Reply to ticket", responses: [new OA\Response(response: 201, description: "Reply sent")])]
    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        $data = $request->validate([
            'message' => 'required|string',
            'attachments.*' => 'nullable|file|max:10240|mimes:jpeg,png,jpg,gif,webp,pdf,doc,docx,xls,xlsx,txt,csv',
        ]);

        $user = $request->user();

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
            'sender_type' => 'admin',
            'sender_id' => $user->id,
            'sender_name' => $user->name,
            'sender_email' => $user->email,
            'message' => $data['message'],
            'attachments' => $attachments ?: null,
        ]);

        // Update ticket
        $ticket->update([
            'message_count' => $ticket->message_count + 1,
            'unread_count' => $ticket->unread_count + 1,
            'first_response_at' => $ticket->first_response_at ?? now(),
        ]);

        AuditLog::log('ticket.admin_reply', 'ticket', 'SupportTicket', $ticket->id, "#{$ticket->ticket_number} admin replied");

        return response()->json($message, 201);
    }

    #[OA\Post(path: "/admin/tickets/{ticket}/status", tags: ["Admin Tickets"], summary: "Update ticket status", responses: [new OA\Response(response: 200, description: "Status updated")])]
    public function updateStatus(Request $request, SupportTicket $ticket): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:open,in_progress,waiting_reply,resolved,closed',
        ]);

        $old = $ticket->status;
        $ticket->update($data);

        // Update timestamps & notify
        if ($data['status'] === 'resolved' && !$ticket->resolved_at) {
            $ticket->update(['resolved_at' => now()]);
            // Notify tenant
            $tenantUser = \App\Models\User::find($ticket->user_id);
            if ($tenantUser) {
                $tenantUser->notify(new TicketResolved($ticket));
            }
        }
        if ($data['status'] === 'closed' && !$ticket->closed_at) {
            $ticket->update(['closed_at' => now()]);
        }

        // Add system message
        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_type' => 'system',
            'message' => "Ticket status changed from \"{$old}\" to \"{$data['status']}\".",
        ]);

        AuditLog::log('ticket.status_change', 'ticket', 'SupportTicket', $ticket->id, "#{$ticket->ticket_number} {$old} → {$data['status']}", ['status' => $old], ['status' => $data['status']]);

        return response()->json($ticket->fresh());
    }

    #[OA\Post(path: "/admin/tickets/{ticket}/reopen", tags: ["Admin Tickets"], summary: "Reopen ticket", responses: [new OA\Response(response: 200, description: "Ticket reopened")])]
    public function reopen(SupportTicket $ticket): JsonResponse
    {
        if ($ticket->status !== 'closed') {
            return response()->json(['error' => 'Only closed tickets can be reopened'], 400);
        }

        $ticket->update([
            'status' => 'open',
            'closed_at' => null,
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_type' => 'system',
            'message' => 'Ticket reopened by admin.',
        ]);

        AuditLog::log('ticket.reopen', 'ticket', 'SupportTicket', $ticket->id, "#{$ticket->ticket_number} reopened by admin");

        return response()->json($ticket->fresh()->load('tenant'));
    }
}
