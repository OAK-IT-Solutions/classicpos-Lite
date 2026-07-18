<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Models\Landlord\ClientUser;
use App\Models\Landlord\OakitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientServiceController extends Controller
{
    public function index(): JsonResponse
    {
        $services = OakitService::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json($services);
    }

    public function subscribed(Request $request): JsonResponse
    {
        /** @var ClientUser $client */
        $client = $request->user();

        $subscription = $client->subscriptions()
            ->with('plan.oakitServices')
            ->whereIn('status', ['active', 'trialing'])
            ->first();

        if (!$subscription) {
            return response()->json([]);
        }

        $services = $subscription->plan->oakitServices;

        return response()->json($services);
    }
}
