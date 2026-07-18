<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Landlord\Tenant;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\SubscriptionPlan;
use App\Models\Landlord\PaymentTransaction;
use App\Models\Landlord\Agent;
use App\Models\Landlord\AgentCommission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class RevenueController extends Controller
{
    #[OA\Get(path: "/admin/revenue/dashboard", tags: ["Admin Revenue"], summary: "Revenue dashboard", responses: [new OA\Response(response: 200, description: "Dashboard data returned")])]
    public function dashboard(Request $request): JsonResponse
    {
        $now = now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfYear = $now->copy()->startOfYear();
        $lastMonth = $now->copy()->subMonth();

        // Total tenants by status
        $tenantStats = Tenant::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // MRR (sum of active subscription plan prices)
        $mrr = Subscription::where('status', 'active')
            ->join('subscription_plans', 'tenant_subscriptions.plan_id', '=', 'subscription_plans.id')
            ->sum('subscription_plans.price_monthly');

        // Last month MRR for comparison
        $lastMonthMrr = Subscription::where('status', 'active')
            ->where('tenant_subscriptions.created_at', '<', $startOfMonth)
            ->join('subscription_plans', 'tenant_subscriptions.plan_id', '=', 'subscription_plans.id')
            ->sum('subscription_plans.price_monthly');

        // Revenue this month
        $revenueThisMonth = PaymentTransaction::where('status', 'success')
            ->where('paid_at', '>=', $startOfMonth)
            ->sum('amount');

        // Revenue this year
        $revenueThisYear = PaymentTransaction::where('status', 'success')
            ->where('paid_at', '>=', $startOfYear)
            ->sum('amount');

        // Active subscriptions
        $activeSubscriptions = Subscription::whereIn('status', ['active', 'trialing'])->count();

        // Open tickets
        $openTickets = DB::connection('landlord')->table('support_tickets')
            ->whereIn('status', ['open', 'in_progress', 'waiting_reply'])
            ->count();

        // Pending commissions
        $pendingCommissions = AgentCommission::where('status', 'pending')->sum('amount');

        return response()->json([
            'tenants' => [
                'total' => $tenantStats->sum(),
                'active' => $tenantStats->get('active', 0),
                'trialing' => $tenantStats->get('trialing', 0),
                'suspended' => $tenantStats->get('suspended', 0),
                'cancelled' => $tenantStats->get('cancelled', 0),
            ],
            'mrr' => (float) $mrr,
            'last_month_mrr' => (float) $lastMonthMrr,
            'mrr_growth' => $lastMonthMrr > 0 ? round((($mrr - $lastMonthMrr) / $lastMonthMrr) * 100, 1) : 0,
            'revenue_this_month' => (float) $revenueThisMonth,
            'revenue_this_year' => (float) $revenueThisYear,
            'active_subscriptions' => $activeSubscriptions,
            'open_tickets' => $openTickets,
            'pending_commissions' => (float) $pendingCommissions,
        ]);
    }

    #[OA\Get(path: "/admin/revenue/summary", tags: ["Admin Revenue"], summary: "Revenue summary", responses: [new OA\Response(response: 200, description: "Summary returned")])]
    public function summary(): JsonResponse
    {
        $summary = PaymentTransaction::selectRaw("
            status,
            count(*) as count,
            sum(amount) as total_amount,
            avg(amount) as avg_amount
        ")
            ->groupBy('status')
            ->get();

        return response()->json($summary);
    }

    #[OA\Get(path: "/admin/revenue/mrr", tags: ["Admin Revenue"], summary: "Monthly recurring revenue", responses: [new OA\Response(response: 200, description: "MRR returned")])]
    public function mrr(): JsonResponse
    {
        // Current MRR by plan
        $mrr = Subscription::where('status', 'active')
            ->join('subscription_plans', 'tenant_subscriptions.plan_id', '=', 'subscription_plans.id')
            ->selectRaw("
                subscription_plans.name as plan_name,
                count(*) as subscribers,
                sum(subscription_plans.price_monthly) as mrr
            ")
            ->groupBy('subscription_plans.name')
            ->get();

        $totalMrr = $mrr->sum('mrr');

        return response()->json([
            'total' => (float) $totalMrr,
            'by_plan' => $mrr,
        ]);
    }

    #[OA\Get(path: "/admin/revenue/arr", tags: ["Admin Revenue"], summary: "Annual recurring revenue", responses: [new OA\Response(response: 200, description: "ARR returned")])]
    public function arr(): JsonResponse
    {
        $mrr = Subscription::where('status', 'active')
            ->join('subscription_plans', 'tenant_subscriptions.plan_id', '=', 'subscription_plans.id')
            ->sum('subscription_plans.price_monthly');

        return response()->json([
            'arr' => (float) $mrr * 12,
            'mrr' => (float) $mrr,
        ]);
    }

    #[OA\Get(path: "/admin/revenue/churn", tags: ["Admin Revenue"], summary: "Revenue churn rate", responses: [new OA\Response(response: 200, description: "Churn data returned")])]
    public function churn(): JsonResponse
    {
        $now = now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();

        // Churned this month (cancelled)
        $churned = Subscription::where('status', 'cancelled')
            ->where('cancelled_at', '>=', $startOfMonth)
            ->count();

        // Active at start of month
        $activeAtStart = Subscription::whereIn('status', ['active', 'trialing'])
            ->where('created_at', '<', $startOfMonth)
            ->count();

        $churnRate = $activeAtStart > 0 ? round(($churned / $activeAtStart) * 100, 2) : 0;

        return response()->json([
            'churned_this_month' => $churned,
            'active_at_start' => $activeAtStart,
            'churn_rate' => $churnRate,
        ]);
    }

    #[OA\Get(path: "/admin/revenue/ltv", tags: ["Admin Revenue"], summary: "Customer lifetime value", responses: [new OA\Response(response: 200, description: "LTV returned")])]
    public function ltv(): JsonResponse
    {
        // Average lifetime value = avg payment per customer * avg months active
        $avgPayment = PaymentTransaction::where('status', 'success')->avg('amount') ?? 0;

        $totalCustomers = Tenant::count();
        $totalRevenue = PaymentTransaction::where('status', 'success')->sum('amount');
        $ltv = $totalCustomers > 0 ? round((float) $totalRevenue / $totalCustomers, 2) : 0;

        return response()->json([
            'avg_ltv' => $ltv,
            'avg_payment' => (float) round($avgPayment, 2),
            'total_customers' => $totalCustomers,
        ]);
    }

    #[OA\Get(path: "/admin/revenue/trend", tags: ["Admin Revenue"], summary: "Revenue trend", responses: [new OA\Response(response: 200, description: "Trend returned")])]
    public function trend(): JsonResponse
    {
        $months = request()->months ?? 12;

        $trend = PaymentTransaction::where('status', 'success')
            ->where('paid_at', '>=', now()->subMonths($months))
            ->selectRaw("date_trunc('month', paid_at) as month, sum(amount) as revenue, count(*) as transactions")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json($trend);
    }

    #[OA\Get(path: "/admin/revenue/by-plan", tags: ["Admin Revenue"], summary: "Revenue by plan", responses: [new OA\Response(response: 200, description: "Revenue by plan returned")])]
    public function byPlan(): JsonResponse
    {
        $byPlan = Subscription::selectRaw("
            subscription_plans.name as plan_name,
            tenant_subscriptions.status,
            count(*) as count
        ")
            ->join('subscription_plans', 'tenant_subscriptions.plan_id', '=', 'subscription_plans.id')
            ->groupBy('subscription_plans.name', 'tenant_subscriptions.status')
            ->get()
            ->groupBy('plan_name')
            ->map(fn ($items) => $items->pluck('count', 'status')->toArray());

        return response()->json($byPlan);
    }
}
