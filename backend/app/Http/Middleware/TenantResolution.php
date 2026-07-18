<?php

namespace App\Http\Middleware;

use App\Models\Landlord\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TenantResolution
{
    /**
     * Resolve the current tenant from the request and switch DB connection.
     *
     * In self-hosted mode (CLASSICPOS_SELF_HOSTED=true), this middleware
     * sets the tenant from SINGLE_TENANT_SLUG without querying the landlord DB.
     *
     * In SaaS mode, the tenant is resolved from:
     * - Subdomain (tenant1.classicpos.app)
     * - Custom domain (custompos.mybusiness.com)
     * - X-Tenant-ID header (for API/SPA calls)
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip tenant resolution for admin and agent API routes — they use
        // landlord DB directly via models with $connection = 'landlord'
        $path = $request->path();
        if (str_contains($path, '/v1/admin') || str_contains($path, '/v1/agent') || str_contains($path, '/v1/client')) {
            return $next($request);
        }

        // In self-hosted mode, skip landlord DB lookup entirely
        if (config('landlord.self_hosted')) {
            $this->resolveSelfHostedTenant();
            return $next($request);
        }

        // SaaS mode: resolve tenant from request
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Tenant not found'], 404);
            }
            return response()->view('errors.404', [
                'message' => 'Tenant not found. Please check the URL or contact support.',
            ], 404);
        }

        // Check tenant status
        if ($tenant->status === 'suspended') {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Account suspended', 'status' => 'suspended'], 403);
            }
            return response()->view('errors.403', [
                'message' => 'This account has been suspended. Please contact support.',
            ], 403);
        }

        if ($tenant->status === 'cancelled') {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Account cancelled', 'status' => 'cancelled'], 403);
            }
            return response()->view('errors.403', [
                'message' => 'This account has been cancelled. Please contact support.',
            ], 403);
        }

        // Check trial expiration
        if ($tenant->status === 'trial' && $tenant->trial_ends_at && $tenant->trial_ends_at->isPast()) {
            $tenant->update(['status' => 'cancelled']);
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Trial expired', 'status' => 'expired'], 403);
            }
            return response()->view('errors.403', [
                'message' => 'Your trial has expired. Please subscribe to continue.',
            ], 403);
        }

        // Switch database connection to tenant's DB
        $this->switchToTenantDatabase($tenant);

        // Store tenant in request for controllers
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }

    /**
     * Resolve tenant from the request based on configured strategy.
     */
    private function resolveTenant(Request $request): ?Tenant
    {
        $strategy = config('landlord.resolution', 'manual');

        return match ($strategy) {
            'subdomain' => $this->resolveFromSubdomain($request),
            'domain' => $this->resolveFromDomain($request),
            'header' => $this->resolveFromHeader($request),
            default => null,
        };
    }

    /**
     * Resolve tenant from subdomain (e.g., tenant1.classicpos.app).
     */
    private function resolveFromSubdomain(Request $request): ?Tenant
    {
        $host = $request->getHost();
        $baseDomain = config('app.domain', 'classicpos.app');

        // Extract subdomain
        $pattern = '/^(?:https?:\/\/)?([a-z0-9-]+)\.' . preg_quote($baseDomain, '/') . '$/i';
        if (!preg_match($pattern, $host, $matches)) {
            return null;
        }

        $slug = $matches[1];

        // Skip common subdomains
        if (in_array($slug, ['www', 'app', 'api', 'mail', 'smtp'])) {
            return null;
        }

        return Tenant::where('slug', $slug)->where('status', '!=', 'cancelled')->first();
    }

    /**
     * Resolve tenant from custom domain (e.g., custompos.mybusiness.com).
     */
    private function resolveFromDomain(Request $request): ?Tenant
    {
        $host = $request->getHost();

        return Tenant::where('domain', $host)
            ->where('status', '!=', 'cancelled')
            ->first();
    }

    /**
     * Resolve tenant from X-Tenant-ID header.
     */
    private function resolveFromHeader(Request $request): ?Tenant
    {
        $headerName = config('landlord.header', 'X-Tenant-ID');
        $tenantId = $request->header($headerName);

        if (!$tenantId) {
            return null;
        }

        return Tenant::where('id', $tenantId)
            ->orWhere('slug', $tenantId)
            ->where('status', '!=', 'cancelled')
            ->first();
    }

    /**
     * In self-hosted mode, resolve the single tenant from config.
     */
    private function resolveSelfHostedTenant(): void
    {
        $slug = config('landlord.self_hosted_slug', 'default');

        // Try to find an actual tenant record (for FK consistency with landlord tables)
        $tenant = null;
        try {
            $tenant = Tenant::where('slug', $slug)->first()
                ?? Tenant::where('status', 'active')->first()
                ?? Tenant::first();
        } catch (\Exception $e) {
            // Landlord DB may not exist — use virtual tenant
        }

        if ($tenant) {
            request()->attributes->set('tenant', $tenant);
            return;
        }

        // Create a virtual tenant that points to the current connection
        $virtualTenant = new \stdClass();
        $virtualTenant->id = 'self-hosted';
        $virtualTenant->slug = $slug;
        $virtualTenant->name = config('app.name', 'ClassicPOS');
        $virtualTenant->status = 'active';
        $virtualTenant->db_name = config('database.connections.pgsql.database');
        $virtualTenant->db_host = config('database.connections.pgsql.host');
        $virtualTenant->db_port = config('database.connections.pgsql.port');

        // Store in request
        request()->attributes->set('tenant', $virtualTenant);
    }

    /**
     * Switch Laravel's default database connection to the tenant's database.
     */
    private function switchToTenantDatabase(Tenant $tenant): void
    {
        $tenantConfig = $tenant->getDatabaseConfig();

        // Register the tenant connection dynamically
        Config::set('database.connections.tenant', $tenantConfig);
        Config::set('database.default', 'tenant');

        // Force the new connection
        DB::purge('tenant');
    }
}
