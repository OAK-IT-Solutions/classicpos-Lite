<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Models\Landlord\ClientUser;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\SubscriptionPlan;
use App\Models\Landlord\SupportTicket;
use App\Models\Landlord\OakitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var ClientUser $client */
        $client = $request->user();

        $subscription = $client->subscriptions()
            ->with('plan')
            ->whereIn('status', ['active', 'trialing'])
            ->first();

        $serviceCount = 0;
        if ($subscription) {
            $serviceCount = $subscription->plan->oakitServices()->count();
        }

        $openTickets = $client->supportTickets()
            ->whereIn('status', ['open', 'in_progress', 'waiting_reply'])
            ->count();

        $totalTickets = $client->supportTickets()->count();

        return response()->json([
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'plan' => [
                    'id' => $subscription->plan->id,
                    'name' => $subscription->plan->name,
                    'slug' => $subscription->plan->slug,
                ],
                'status' => $subscription->status,
                'billing_cycle' => $subscription->billing_cycle,
                'amount' => $subscription->amount,
                'starts_at' => $subscription->starts_at,
                'ends_at' => $subscription->ends_at,
                'trial_ends_at' => $subscription->trial_ends_at,
                'days_until_renewal' => $subscription->daysUntilRenewal(),
            ] : null,
            'stats' => [
                'subscribed_services' => $serviceCount,
                'open_tickets' => $openTickets,
                'total_tickets' => $totalTickets,
            ],
            'recent_tickets' => $client->supportTickets()
                ->latest()
                ->take(5)
                ->get(['id', 'ticket_number', 'subject', 'status', 'priority', 'created_at']),
        ]);
    }
}
