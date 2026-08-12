<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Landlord\SubscriptionPlan;
use App\Models\Landlord\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Public Plans', description: 'Public subscription plan listing (no auth required)')]
class PublicPlanController extends Controller
{
    #[OA\Get(
        path: '/api/v1/plans',
        tags: ['Public Plans'],
        summary: 'List active subscription plans with pricing',
        parameters: [
            new OA\Parameter(name: 'currency', in: 'query', schema: new OA\Schema(type: 'string', default: 'USD'), description: 'Currency code for pricing'),
        ],
        responses: [new OA\Response(response: 200, description: 'Plans with localized pricing')]
    )]
    public function index(Request $request): JsonResponse
    {
        $currencyCode = $request->query('currency', 'USD');
        $currency = Currency::where('code', $currencyCode)->where('is_active', true)->first()
            ?? Currency::where('is_default', true)->first()
            ?? Currency::where('code', 'USD')->first();

        $plans = SubscriptionPlan::where('is_active', true)
            ->with(['planFeatures' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->withCount('subscriptions')
            ->orderBy('sort_order')
            ->get()
            ->map(function ($plan) use ($currency) {
                $monthlyUsd = (float) $plan->price_monthly;
                $yearlyUsd = $plan->getPriceForCycle('yearly');

                $rate = (float) ($currency->exchange_rate_to_usd ?? 1);

                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'description' => $plan->description,
                    'price_monthly' => round($monthlyUsd * $rate, $currency->decimal_places ?? 2),
                    'price_yearly' => round($yearlyUsd * $rate, $currency->decimal_places ?? 2),
                    'price_monthly_usd' => $monthlyUsd,
                    'price_yearly_usd' => $yearlyUsd,
                    'currency' => $currency->code,
                    'currency_symbol' => $currency->symbol,
                    'savings_percent' => $plan->getSavingsPercent(),
                    'discount_percent_yearly' => $plan->discount_percent_yearly,
                    'max_branches' => $plan->max_branches,
                    'max_users_per_branch' => $plan->max_users_per_branch,
                    'max_devices_per_branch' => $plan->max_devices_per_branch,
                    'is_popular' => $plan->is_popular,
                    'highlight_color' => $plan->highlight_color,
                    'cta_text' => $plan->cta_text,
                    'is_default' => $plan->is_default,
                    'sort_order' => $plan->sort_order,
                    'features' => $plan->planFeatures->map(fn ($f) => [
                        'id' => $f->id,
                        'name' => $f->name,
                        'slug' => $f->slug,
                        'description' => $f->description,
                        'icon' => $f->icon,
                        'group_name' => $f->group_name,
                        'is_highlighted' => (bool) $f->pivot?->is_highlighted,
                    ]),
                ];
            });

        return response()->json([
            'plans' => $plans,
            'currency' => [
                'code' => $currency->code,
                'name' => $currency->name,
                'symbol' => $currency->symbol,
                'decimal_places' => $currency->decimal_places,
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/v1/plans/{slug}',
        tags: ['Public Plans'],
        summary: 'Get a single plan by slug',
        parameters: [
            new OA\Parameter(name: 'slug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'currency', in: 'query', schema: new OA\Schema(type: 'string', default: 'USD')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Plan details'),
            new OA\Response(response: 404, description: 'Plan not found'),
        ]
    )]
    public function show(Request $request, string $slug): JsonResponse
    {
        $currencyCode = $request->query('currency', 'USD');
        $currency = Currency::where('code', $currencyCode)->where('is_active', true)->first()
            ?? Currency::where('is_default', true)->first()
            ?? Currency::where('code', 'USD')->first();

        $plan = SubscriptionPlan::where('slug', $slug)
            ->where('is_active', true)
            ->with(['planFeatures' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->withCount('subscriptions')
            ->firstOrFail();

        $monthlyUsd = (float) $plan->price_monthly;
        $yearlyUsd = $plan->getPriceForCycle('yearly');

        $rate = (float) ($currency->exchange_rate_to_usd ?? 1);

        return response()->json([
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'description' => $plan->description,
            'price_monthly' => round($monthlyUsd * $rate, $currency->decimal_places ?? 2),
            'price_yearly' => round($yearlyUsd * $rate, $currency->decimal_places ?? 2),
            'price_monthly_usd' => $monthlyUsd,
            'price_yearly_usd' => $yearlyUsd,
            'currency' => $currency->code,
            'currency_symbol' => $currency->symbol,
            'savings_percent' => $plan->getSavingsPercent(),
            'discount_percent_yearly' => $plan->discount_percent_yearly,
            'max_branches' => $plan->max_branches,
            'max_users_per_branch' => $plan->max_users_per_branch,
            'max_devices_per_branch' => $plan->max_devices_per_branch,
            'is_popular' => $plan->is_popular,
            'highlight_color' => $plan->highlight_color,
            'cta_text' => $plan->cta_text,
            'is_default' => $plan->is_default,
            'sort_order' => $plan->sort_order,
            'features' => $plan->planFeatures->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->name,
                'slug' => $f->slug,
                'description' => $f->description,
                'icon' => $f->icon,
                'group_name' => $f->group_name,
                'is_highlighted' => (bool) $f->pivot?->is_highlighted,
            ]),
            'subscriptions_count' => $plan->subscriptions_count,
        ]);
    }

    #[OA\Get(
        path: '/api/v1/currencies',
        tags: ['Public Plans'],
        summary: 'List active currencies',
        responses: [new OA\Response(response: 200, description: 'Active currencies')]
    )]
    public function currencies(): JsonResponse
    {
        $currencies = Currency::where('is_active', true)->orderBy('is_default', 'desc')->get();
        return response()->json(['currencies' => $currencies]);
    }
}
