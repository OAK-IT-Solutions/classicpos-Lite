<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Models\Landlord\ClientUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientBillingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var ClientUser $client */
        $client = $request->user();

        $transactions = $client->paymentTransactions()
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json($transactions);
    }
}
