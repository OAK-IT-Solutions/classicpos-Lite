<?php

namespace App\Providers;

use App\Auth\AgentAwareUserProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Auth::provider('agent_aware', function ($app, array $config) {
            return new AgentAwareUserProvider($app['hash'], $config['model']);
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute((int) env('API_RATE_LIMIT', 120))
                ->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'error' => [
                            'code' => 'ERR_RATE_LIMIT',
                            'message' => 'Too many requests. Please try again later.',
                            'timestamp' => now()->toIso8601String(),
                        ],
                    ], 429);
                });
        });
    }
}
