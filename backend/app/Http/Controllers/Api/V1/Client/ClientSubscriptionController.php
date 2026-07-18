<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Models\Landlord\ClientUser;
use App\Models\Landlord\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientSubscriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var ClientUser $client */
        $client = $request->user();

        $subscriptions = $client->subscriptions()
            ->with('plan')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($subscriptions);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        /** @var ClientUser $client */
        $client = $request->user();

        $subscription = $client->subscriptions()
            ->with('plan.oakitServices')
            ->findOrFail($id);

        return response()->json($subscription);
    }
}
