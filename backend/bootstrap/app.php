<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: [
            __DIR__.'/../routes/api.php',
            __DIR__.'/../routes/admin.php',
            __DIR__.'/../routes/agent.php',
            __DIR__.'/../routes/client.php',
        ],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withProviders([
        \App\Providers\LandlordServiceProvider::class,
        \App\Providers\AuthServiceProvider::class,
        \App\Providers\EventServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        // Trust all proxies (app runs behind nginx + cloudflare tunnel)
        $middleware->trustProxies(at: '*');

        // TenantResolution runs before auth — resolves which DB to use
        $middleware->web(prepend: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\TenantResolution::class,
        ]);

        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\AuditActivity::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\TenantResolution::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
            'tenant' => \App\Http\Middleware\TenantResolution::class,
            'super_admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
            'agent' => \App\Http\Middleware\EnsureAgent::class,
            'client' => \App\Http\Middleware\EnsureClientUser::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        Integration::handles($exceptions);

        $exceptions->render(function (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => [
                    'code' => 'ERR_VALIDATION',
                    'message' => 'The given data was invalid.',
                    'details' => $e->errors(),
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 422);
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->header('X-Inertia') || !$request->expectsJson()) {
                return redirect()->guest(route('login'));
            }
            return response()->json([
                'error' => [
                    'code' => 'ERR_UNAUTHORIZED',
                    'message' => 'Unauthenticated.',
                    'details' => [],
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 401);
        });
    })->create();
