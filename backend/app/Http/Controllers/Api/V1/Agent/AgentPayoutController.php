<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Http\Controllers\Controller;
use App\Models\Landlord\Agent;
use App\Models\Landlord\AgentCommission;
use App\Models\Landlord\PaymentTransaction;
use App\Models\Landlord\PlatformSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class AgentPayoutController extends Controller
{
    #[OA\Get(path: "/agent/payouts", tags: ["Agent"], summary: "List agent payouts", responses: [new OA\Response(response: 200, description: "Payouts listed")])]
    public function index(Request $request): JsonResponse
    {
        $agent = $request->attributes->get('agent');

        $query = PaymentTransaction::where('agent_id', $agent->id)
            ->where('type', 'payout')
            ->orderByDesc('created_at');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $payouts = $query->paginate($request->input('per_page', 15));

        return response()->json($payouts);
    }

    #[OA\Post(path: "/agent/payouts", tags: ["Agent"], summary: "Request payout", responses: [new OA\Response(response: 201, description: "Payout requested")])]
    public function requestPayout(Request $request): JsonResponse
    {
        $agent = $request->attributes->get('agent');
        $minPayout = (float) PlatformSetting::get('agent_min_payout', 1);

        $request->validate([
            'amount' => "required|numeric|min:{$minPayout}|max:{$agent->pending_earnings}",
            'method' => 'required|in:bank,mobile_money,pesapal',
            'account_details' => 'required|array',
            'account_details.bank_name' => 'required_if:method,bank|nullable|string',
            'account_details.account_number' => 'required_if:method,bank|nullable|string',
            'account_details.phone' => 'required_if:method,mobile_money|nullable|string',
            'account_details.network' => 'required_if:method,mobile_money|nullable|string',
        ]);

        if ($agent->pending_earnings < $minPayout) {
            return response()->json(['error' => "Insufficient pending earnings for payout. Minimum payout is {$minPayout}."], 422);
        }

        $amount = round($request->input('amount'), 2);

        if ($amount > $agent->pending_earnings) {
            return response()->json(['error' => 'Payout amount exceeds pending earnings.'], 422);
        }

        $payout = DB::transaction(function () use ($agent, $request, $amount) {
            // Create payout transaction
            $transaction = PaymentTransaction::create([
                'agent_id' => $agent->id,
                'type' => 'payout',
                'amount' => $amount,
                'currency' => PlatformSetting::get('default_currency', 'KES'),
                'gateway' => $request->input('method'),
                'gateway_ref' => 'PAY-' . Str::random(12),
                'status' => 'pending',
                'metadata' => [
                    'account_details' => $request->input('account_details'),
                    'requested_at' => now()->toIso8601String(),
                ],
            ]);

            // Update agent earnings
            $agent->update([
                'pending_earnings' => $agent->pending_earnings - $amount,
            ]);

            // Mark oldest pending commissions as paid
            $pendingCommissions = AgentCommission::where('agent_id', $agent->id)
                ->where('status', 'pending')
                ->orderBy('created_at')
                ->limit(100)
                ->get();

            $remaining = $amount;
            foreach ($pendingCommissions as $commission) {
                if ($remaining <= 0) break;
                if ($commission->amount <= $remaining) {
                    $commission->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                        'payout_reference' => $transaction->gateway_ref,
                    ]);
                    $remaining -= $commission->amount;
                }
            }

            // Update agent totals
            $agent->update([
                'paid_earnings' => $agent->paid_earnings + $amount,
            ]);

            return $transaction;
        });

        return response()->json([
            'id' => $payout->id,
            'amount' => $payout->amount,
            'gateway' => $payout->gateway,
            'gateway_ref' => $payout->gateway_ref,
            'status' => $payout->status,
            'created_at' => $payout->created_at->toIso8601String(),
        ], 201);
    }

    #[OA\Get(path: "/agent/payouts/{id}", tags: ["Agent"], summary: "Get payout", responses: [new OA\Response(response: 200, description: "Payout returned")])]
    public function show(Request $request, string $id): JsonResponse
    {
        $agent = $request->attributes->get('agent');

        $payout = PaymentTransaction::where('agent_id', $agent->id)
            ->where('type', 'payout')
            ->findOrFail($id);

        return response()->json([
            'id' => $payout->id,
            'amount' => $payout->amount,
            'currency' => $payout->currency,
            'gateway' => $payout->gateway,
            'gateway_ref' => $payout->gateway_ref,
            'status' => $payout->status,
            'metadata' => $payout->metadata,
            'created_at' => $payout->created_at->toIso8601String(),
        ]);
    }
}
