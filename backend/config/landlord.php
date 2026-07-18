<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Landlord Database Connection
    |--------------------------------------------------------------------------
    |
    | The connection name for the landlord (global SaaS) database.
    | This stores tenants, subscriptions, agents, tickets, and audit logs.
    | Tenant databases store the actual POS business data.
    |
    */
    'connection' => env('LANDLORD_DB_CONNECTION', 'landlord'),

    /*
    |--------------------------------------------------------------------------
    | Tenant Resolution Strategy
    |--------------------------------------------------------------------------
    |
    | How to identify which tenant a request belongs to.
    | Options: 'subdomain', 'domain', 'header', 'path', 'manual'
    |
    | subdomain — tenant1.classicpos.app
    | domain   — custompos.mybusiness.com
    | header   — X-Tenant-ID header (for API/SPA)
    | path     — /tenant/{slug}/pos (not recommended)
    | manual   — set manually (for self-hosted single-tenant)
    |
    */
    'resolution' => env('TENANT_RESOLUTION', 'manual'),

    /*
    |--------------------------------------------------------------------------
    | Tenant Header
    |--------------------------------------------------------------------------
    |
    | The HTTP header name used to identify the tenant when using 'header'
    | resolution strategy.
    |
    */
    'header' => 'X-Tenant-ID',

    /*
    |--------------------------------------------------------------------------
    | Self-Hosted Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, the application runs as a single-tenant instance.
    | The tenant is resolved automatically from the SINGLE_TENANT_SLUG env var.
    | No landlord database is needed — all data lives in one database.
    |
    */
    'self_hosted' => env('CLASSICPOS_SELF_HOSTED', true),

    /*
    |--------------------------------------------------------------------------
    | Self-Hosted Tenant Slug
    |--------------------------------------------------------------------------
    |
    | The tenant slug used in self-hosted mode. This is the only tenant
    | in the system when CLASSICPOS_SELF_HOSTED=true.
    |
    */
    'self_hosted_slug' => env('SINGLE_TENANT_SLUG', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Tenant Database Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix applied to dynamically created tenant database names.
    | Example: 'classicpos_tenant_' + slug = 'classicpos_tenant_mybusiness'
    |
    */
    'db_prefix' => env('TENANT_DB_PREFIX', 'classicpos_tenant_'),

    /*
    |--------------------------------------------------------------------------
    | Tenant Provisioning
    |--------------------------------------------------------------------------
    |
    | Settings for when a new tenant is created.
    |
    */
    'provisioning' => [
        'run_migrations' => true,
        'seed_data' => true,
        'default_admin_email' => 'admin@classicpos.app',
    ],

    /*
    |--------------------------------------------------------------------------
    | Trial Settings
    |--------------------------------------------------------------------------
    |
    | Default trial configuration for new tenants.
    |
    */
    'trial' => [
        'enabled' => true,
        'duration_days' => 14,
        'extension_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model used to resolve tenants.
    |
    */
    'model' => App\Models\Landlord\Tenant::class,
];
