<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Models\Landlord\ClientUser;
use App\Models\Landlord\SupportTicket;
use App\Models\Landlord\TicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var ClientUser $client */
        $client = $request->user();

        $tickets = $client->supportTickets()
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->priority, fn ($q, $p) => $q->where('priority', $p))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('subject', 'ilike', "%{$s}%")->orWhere('ticket_number', 'ilike', "%{$s}%")))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json($tickets);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'category' => 'nullable|string|max:100',
        ]);

        /** @var ClientUser $client */
        $client = $request->user();

        $ticketNumber = 'TKT-' . strtoupper(uniqid());

        $ticket = SupportTicket::create([
            'client_user_id' => $client->id,
            'subject' => $data['subject'],
            'description' => $data['description'],
            'ticket_number' => $ticketNumber,
            'status' => 'open',
            'priority' => $data['priority'] ?? 'medium',
            'category' => $data['category'] ?? null,
            'message_count' => 1,
            'unread_count' => 0,
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_type' => 'client',
            'sender_id' => $client->id,
            'sender_name' => $client->name,
            'sender_email' => $client->email,
            'message' => $data['description'],
        ]);

        return response()->json($ticket, 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        /** @var ClientUser $client */
        $client = $request->user();

        $ticket = $client->supportTickets()
            ->with('messages')
            ->findOrFail($id);

        return response()->json($ticket);
    }

    public function reply(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'message' => 'required|string',
        ]);

        /** @var ClientUser $client */
        $client = $request->user();

        $ticket = $client->supportTickets()->findOrFail($id);

        $message = TicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_type' => 'client',
            'sender_id' => $client->id,
            'sender_name' => $client->name,
            'sender_email' => $client->email,
            'message' => $data['message'],
        ]);

        $ticket->update([
            'message_count' => $ticket->message_count + 1,
            'unread_count' => $ticket->unread_count + 1,
        ]);

        return response()->json($message, 201);
    }
}
