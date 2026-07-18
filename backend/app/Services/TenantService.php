<?php

namespace App\Services;

use App\Models\Landlord\Tenant;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\SubscriptionPlan;
use App\Models\Landlord\Agent;
use App\Models\Landlord\AgentReferral;
use App\Models\Landlord\PaymentTransaction;
use App\Models\Landlord\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantService
{
    /**
     * Create a new tenant with isolated database.
     */
    public function createTenant(array $data): Tenant
    {
        // Generate slug and database name
        $slug = $data['slug'] ?? Str::slug($data['name']);
        $dbName = config('landlord.db_prefix') . $slug;

        // Create tenant record inside a transaction
        $tenant = DB::connection('landlord')->transaction(function () use ($data, $slug, $dbName) {
            $tenant = Tenant::create([
                'name' => $data['name'],
                'slug' => $slug,
                'domain' => $data['domain'] ?? null,
                'db_host' => config('database.connections.pgsql.host'),
                'db_port' => config('database.connections.pgsql.port'),
                'db_name' => $dbName,
                'db_username' => config('database.connections.pgsql.username'),
                'db_password' => config('database.connections.pgsql.password'),
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(config('landlord.trial.duration_days', 14)),
                'business_name' => $data['business_name'] ?? null,
                'business_email' => $data['email'] ?? null,
                'business_phone' => $data['phone'] ?? null,
                'created_by_agent_id' => $data['agent_id'] ?? null,
                'referred_by_agent_id' => $data['referred_by_agent_id'] ?? null,
            ]);

            // Create default subscription inside same transaction
            $this->createDefaultSubscription($tenant, $data);

            return $tenant;
        });

        // Provision database OUTSIDE the transaction (PostgreSQL doesn't allow DDL in transactions)
        $this->provisionDatabase($tenant, $data);

        // Agent referral and audit log outside transaction
        DB::connection('landlord')->transaction(function () use ($tenant, $data) {
            if (!empty($data['agent_id'])) {
                $this->createReferral($tenant, $data['agent_id']);
            }

            AuditLog::log(
                'tenant.create',
                'tenant',
                'Tenant',
                $tenant->id,
                $tenant->name,
                null,
                $tenant->toArray()
            );
        });

        return $tenant;
    }

    /**
     * Provision a new database for a tenant (create DB, run migrations, seed).
     */
    private function provisionDatabase(Tenant $tenant, array $data): void
    {
        $dbName = $tenant->db_name;
        $dbUser = $tenant->db_username;

        $this->createDatabase($dbName, $dbUser);

        // Run migrations on the new tenant database
        if (config('landlord.provisioning.run_migrations', true)) {
            $this->runTenantMigrations($tenant);
        }

        // Seed with demo data
        if (config('landlord.provisioning.seed_data', true)) {
            $this->seedTenantDatabase($tenant, $data);
        }
    }

    /**
     * Run Laravel migrations on the tenant database.
     */
    private function runTenantMigrations(Tenant $tenant): void
    {
        // Temporarily switch to the tenant database
        $originalDefault = config('database.default');

        Config::set('database.connections.tenant', $tenant->getDatabaseConfig());
        Config::set('database.default', 'tenant');

        try {
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--force' => true,
            ]);
        } finally {
            Config::set('database.default', $originalDefault);
            DB::purge('tenant');
        }
    }

    /**
     * Seed the tenant database with initial data.
     */
    private function seedTenantDatabase(Tenant $tenant, array $data): void
    {
        $originalDefault = config('database.default');

        Config::set('database.connections.tenant', $tenant->getDatabaseConfig());
        Config::set('database.default', 'tenant');

        try {
            Artisan::call('db:seed', [
                '--database' => 'tenant',
                '--force' => true,
            ]);

            // Create admin user from registration data
            if (!empty($data['email']) && !empty($data['password'])) {
                $this->createTenantAdmin($tenant, $data);
            }
        } finally {
            Config::set('database.default', $originalDefault);
            DB::purge('tenant');
        }
    }

    /**
     * Create the admin user in the tenant database.
     */
    private function createTenantAdmin(Tenant $tenant, array $data): void
    {
        // This runs against the tenant database
        $branch = \App\Models\Branch::create([
            'name' => $data['business_name'] ?? $data['name'] . ' - Main Branch',
            'location' => $data['business_name'] ?? 'Main Location',
            'timezone' => 'Africa/Nairobi',
            'cloud_sync_status' => 'pending',
            'business_type' => 'bar_restaurant',
        ]);

        $adminUser = \App\Models\User::create([
            'name' => $data['name'] ?? 'Admin',
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_active' => true,
            'is_protected' => true,
            'branch_id' => $branch->id,
        ]);

        // Assign admin role with branch_id
        $adminRole = \App\Models\Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminUser->roles()->attach($adminRole->id, ['branch_id' => $branch->id]);
        }

        // Add to branch_user table
        \Illuminate\Support\Facades\DB::table('branch_user')->insert([
            'user_id' => $adminUser->id,
            'branch_id' => $branch->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Create a default subscription for a new tenant.
     */
    private function createDefaultSubscription(Tenant $tenant, array $data): void
    {
        $planSlug = !empty($data['plan']) ? $data['plan'] : 'starter';
        $plan = SubscriptionPlan::where('slug', $planSlug)->where('is_active', true)->first()
            ?? SubscriptionPlan::where('is_default', true)->where('is_active', true)->first();

        if (!$plan) return;

        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'trialing',
            'billing_cycle' => 'monthly',
            'amount' => $plan->price_monthly,
            'original_amount' => $plan->price_monthly,
            'starts_at' => now(),
            'ends_at' => now()->addDays(config('landlord.trial.duration_days', 14)),
            'trial_ends_at' => now()->addDays(config('landlord.trial.duration_days', 14)),
        ]);
    }

    /**
     * Create a referral record for an agent.
     */
    private function createReferral(Tenant $tenant, string $agentId): void
    {
        $agent = Agent::find($agentId);
        if (!$agent) return;

        AgentReferral::create([
            'agent_id' => $agentId,
            'tenant_id' => $tenant->id,
            'referral_code' => $agent->code,
            'registered_at' => now(),
            'trial_started_at' => now(),
        ]);

        $agent->increment('total_referrals');
    }

    /**
     * Suspend a tenant.
     */
    public function suspendTenant(string $tenantId, ?string $reason = null): Tenant
    {
        $tenant = Tenant::findOrFail($tenantId);

        DB::connection('landlord')->transaction(function () use ($tenant, $reason) {
            $oldStatus = $tenant->status;
            $tenant->update([
                'status' => 'suspended',
                'suspended_at' => now(),
            ]);

            AuditLog::log(
                'tenant.suspend',
                'tenant',
                'Tenant',
                $tenant->id,
                $tenant->name,
                ['status' => $oldStatus],
                ['status' => 'suspended', 'reason' => $reason]
            );
        });

        return $tenant->fresh();
    }

    /**
     * Reactivate a suspended tenant.
     */
    public function activateTenant(string $tenantId): Tenant
    {
        $tenant = Tenant::findOrFail($tenantId);

        DB::connection('landlord')->transaction(function () use ($tenant) {
            $oldStatus = $tenant->status;
            $tenant->update([
                'status' => 'active',
                'suspended_at' => null,
            ]);

            AuditLog::log(
                'tenant.activate',
                'tenant',
                'Tenant',
                $tenant->id,
                $tenant->name,
                ['status' => $oldStatus],
                ['status' => 'active']
            );
        });

        return $tenant->fresh();
    }

    /**
     * Cancel a tenant.
     */
    public function cancelTenant(string $tenantId, ?string $reason = null): Tenant
    {
        $tenant = Tenant::findOrFail($tenantId);

        DB::connection('landlord')->transaction(function () use ($tenant, $reason) {
            $oldStatus = $tenant->status;
            $tenant->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            // Cancel active subscription
            $tenant->subscription()
                ->whereIn('status', ['active', 'trialing'])
                ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            AuditLog::log(
                'tenant.cancel',
                'tenant',
                'Tenant',
                $tenant->id,
                $tenant->name,
                ['status' => $oldStatus],
                ['status' => 'cancelled', 'reason' => $reason]
            );
        });

        return $tenant->fresh();
    }

    /**
     * Delete a tenant and its database (soft delete + DB drop).
     */
    public function deleteTenant(string $tenantId): bool
    {
        $tenant = Tenant::findOrFail($tenantId);

        // Drop database outside transaction (DDL not allowed in transactions)
        $this->dropDatabase($tenant->db_name);

        return DB::connection('landlord')->transaction(function () use ($tenant) {
            AuditLog::log(
                'tenant.delete',
                'tenant',
                'Tenant',
                $tenant->id,
                $tenant->name
            );

            return $tenant->delete();
        });
    }

    /**
     * Create a PostgreSQL database using a raw connection.
     */
    private function createDatabase(string $dbName, string $dbUser): void
    {
        $pdo = $this->getPostgresConnection();
        $pdo->exec("CREATE DATABASE \"{$dbName}\"");
        $pdo->exec("GRANT ALL PRIVILEGES ON DATABASE \"{$dbName}\" TO \"{$dbUser}\"");
        $pdo->exec("ALTER DATABASE \"{$dbName}\" OWNER TO \"{$dbUser}\"");
    }

    /**
     * Drop a PostgreSQL database using a raw connection.
     */
    private function dropDatabase(string $dbName): void
    {
        try {
            $pdo = $this->getPostgresConnection();
            $pdo->exec(
                "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '{$dbName}' AND pid <> pg_backend_pid()"
            );
            $pdo->exec("DROP DATABASE IF EXISTS \"{$dbName}\"");
        } catch (\Exception $e) {
            \Log::error("Failed to drop database: {$dbName}", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get a raw PDO connection to the postgres default database.
     */
    private function getPostgresConnection(): \PDO
    {
        $host = config('database.connections.landlord.host');
        $port = config('database.connections.landlord.port');
        $user = config('database.connections.landlord.username');
        $pass = config('database.connections.landlord.password');
        return new \PDO("pgsql:host={$host};port={$port};dbname=postgres", $user, $pass);
    }

    /**
     * Get tenant's database connection config.
     */
    public function getTenantConnection(Tenant $tenant): array
    {
        return $tenant->getDatabaseConfig();
    }

    /**
     * Generate a unique tenant slug.
     */
    public function generateSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 1;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter++;
        }

        return $slug;
    }
}
