<?php

namespace Database\Seeders;

use App\Models\Landlord\AdminUser;
use App\Models\Landlord\Agent;
use App\Models\Landlord\AgentUser;
use App\Models\Landlord\SubscriptionPlan;
use App\Models\Landlord\SubscriptionFeature;
use App\Models\Landlord\Currency;
use App\Models\Landlord\PlatformSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LandlordSeeder extends Seeder
{
    /**
     * Seed the landlord database with default subscription plans and settings.
     *
     * Run with: php artisan db:seed --class=LandlordSeeder --database=landlord
     * Or: php artisan seed:landlord
     */
    public function run(): void
    {
        $this->seedFeatures();
        $this->seedPlans();
        $this->seedSettings();
        $this->seedCurrencies();
        $this->seedAdminUser();
        $this->seedAgentUser();
    }

    private function seedFeatures(): void
    {
        $features = [
            ['name' => 'Full POS Functionality', 'slug' => 'full_pos', 'description' => 'Complete point-of-sale with product grid, cart, and checkout', 'icon' => 'ShoppingCart', 'group_name' => 'POS', 'sort_order' => 1],
            ['name' => 'Unlimited Transactions', 'slug' => 'unlimited_transactions', 'description' => 'Process unlimited sales transactions', 'icon' => 'Activity', 'group_name' => 'POS', 'sort_order' => 2],
            ['name' => 'Barcode Scanning', 'slug' => 'barcode_scanning', 'description' => 'USB barcode scanner support out of the box', 'icon' => 'ScanLine', 'group_name' => 'POS', 'sort_order' => 3],
            ['name' => 'Offline Mode', 'slug' => 'offline_mode', 'description' => 'Continue selling without internet, auto-sync when reconnected', 'icon' => 'Wifi', 'group_name' => 'Infrastructure', 'sort_order' => 4],
            ['name' => 'Single Branch', 'slug' => 'single_branch', 'description' => 'Operate one branch location', 'icon' => 'Building2', 'group_name' => 'Multi-Branch', 'sort_order' => 5],
            ['name' => 'Multi-Branch Support', 'slug' => 'multi_branch', 'description' => 'Manage multiple branches from one dashboard', 'icon' => 'Building2', 'group_name' => 'Multi-Branch', 'sort_order' => 6],
            ['name' => 'Unlimited Branches', 'slug' => 'unlimited_branches', 'description' => 'No limit on the number of branches', 'icon' => 'Globe', 'group_name' => 'Multi-Branch', 'sort_order' => 7],
            ['name' => 'Inventory Management', 'slug' => 'inventory_management', 'description' => 'Real-time stock tracking with low stock alerts', 'icon' => 'Package', 'group_name' => 'Inventory', 'sort_order' => 8],
            ['name' => 'Inventory Transfer', 'slug' => 'inventory_transfer', 'description' => 'Transfer stock between warehouses with audit trail', 'icon' => 'Truck', 'group_name' => 'Inventory', 'sort_order' => 9],
            ['name' => 'Purchase Orders & GRN', 'slug' => 'purchase_orders', 'description' => 'Create POs and receive goods with GRN', 'icon' => 'FileText', 'group_name' => 'Inventory', 'sort_order' => 10],
            ['name' => 'Basic Reporting', 'slug' => 'basic_reporting', 'description' => 'Essential sales and transaction reports', 'icon' => 'BarChart3', 'group_name' => 'Reports', 'sort_order' => 11],
            ['name' => 'Advanced Reporting', 'slug' => 'advanced_reporting', 'description' => '13+ reports with AG Grid, CSV export, chart visualizations', 'icon' => 'BarChart3', 'group_name' => 'Reports', 'sort_order' => 12],
            ['name' => 'Customer Management', 'slug' => 'customer_management', 'description' => 'Customer database with purchase history', 'icon' => 'Users', 'group_name' => 'Customers', 'sort_order' => 13],
            ['name' => 'Customer Loyalty', 'slug' => 'customer_loyalty', 'description' => 'Points-based loyalty program with configurable tiers', 'icon' => 'Gift', 'group_name' => 'Customers', 'sort_order' => 14],
            ['name' => 'Multi-Payment Gateways', 'slug' => 'multi_payment', 'description' => 'Cash, card, M-Pesa, Airtel Money, Pesapal integration', 'icon' => 'CreditCard', 'group_name' => 'Payments', 'sort_order' => 15],
            ['name' => 'Promotions & Discounts', 'slug' => 'promotions', 'description' => 'Promo codes, percentage/flat discounts, usage limits', 'icon' => 'Percent', 'group_name' => 'Marketing', 'sort_order' => 16],
            ['name' => 'Invoicing & Quotes', 'slug' => 'invoicing', 'description' => 'Create quotes and invoices, convert quotes to invoices', 'icon' => 'FileText', 'group_name' => 'Documents', 'sort_order' => 17],
            ['name' => 'Returns & Refunds', 'slug' => 'returns', 'description' => 'Full return flow with condition tracking and restocking', 'icon' => 'RefreshCw', 'group_name' => 'Operations', 'sort_order' => 18],
            ['name' => 'API Access', 'slug' => 'api_access', 'description' => 'RESTful API with Sanctum token auth for integrations', 'icon' => 'MessageSquareCode', 'group_name' => 'Integrations', 'sort_order' => 19],
            ['name' => 'Custom Integrations', 'slug' => 'custom_integrations', 'description' => 'Custom development and third-party integrations', 'icon' => 'MessageSquareCode', 'group_name' => 'Integrations', 'sort_order' => 20],
            ['name' => 'Role-Based Access', 'slug' => 'role_based_access', 'description' => 'Granular permissions for admin, manager, cashier roles', 'icon' => 'Shield', 'group_name' => 'Security', 'sort_order' => 21],
            ['name' => 'Audit Trail', 'slug' => 'audit_trail', 'description' => 'Complete audit log for compliance and troubleshooting', 'icon' => 'Shield', 'group_name' => 'Security', 'sort_order' => 22],
            ['name' => 'Email Support', 'slug' => 'email_support', 'description' => 'Email-based technical support', 'icon' => 'Mail', 'group_name' => 'Support', 'sort_order' => 23],
            ['name' => 'Priority Support', 'slug' => 'priority_support', 'description' => 'Priority email, chat, and phone support', 'icon' => 'HeadphonesIcon', 'group_name' => 'Support', 'sort_order' => 24],
            ['name' => '24/7 Support', 'slug' => '24_7_support', 'description' => 'Round-the-clock phone, chat, and email support', 'icon' => 'HeadphonesIcon', 'group_name' => 'Support', 'sort_order' => 25],
            ['name' => 'Managed Backups', 'slug' => 'managed_backups', 'description' => 'Automatic database backups with retention policy', 'icon' => 'Database', 'group_name' => 'Infrastructure', 'sort_order' => 26],
            ['name' => 'Dedicated Infrastructure', 'slug' => 'dedicated_infrastructure', 'description' => 'Dedicated servers and infrastructure for your business', 'icon' => 'Server', 'group_name' => 'Infrastructure', 'sort_order' => 27],
            ['name' => 'White-Label', 'slug' => 'white_label', 'description' => 'Brand the application with your own logo and colors', 'icon' => 'Palette', 'group_name' => 'Customization', 'sort_order' => 28],
            ['name' => 'SLA Guarantee', 'slug' => 'sla_guarantee', 'description' => 'Service Level Agreement with guaranteed uptime', 'icon' => 'Shield', 'group_name' => 'Support', 'sort_order' => 29],
            ['name' => 'Custom Development', 'slug' => 'custom_development', 'description' => 'Bespoke feature development for your business needs', 'icon' => 'Code2', 'group_name' => 'Customization', 'sort_order' => 30],
        ];

        foreach ($features as $data) {
            SubscriptionFeature::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }
    }

    private function seedPlans(): void
    {
        $starter = SubscriptionPlan::updateOrCreate(
            ['slug' => 'starter'],
            [
                'name' => 'Starter',
                'description' => 'Perfect for single-location businesses just getting started with POS.',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'max_branches' => 1,
                'max_users_per_branch' => 3,
                'max_devices_per_branch' => 2,
                'features' => ['basic_reporting', 'offline_mode', 'single_branch'],
                'is_active' => true,
                'is_default' => true,
                'is_popular' => false,
                'cta_text' => 'Start Free',
                'sort_order' => 1,
            ]
        );

        $professional = SubscriptionPlan::updateOrCreate(
            ['slug' => 'professional'],
            [
                'name' => 'Professional',
                'description' => 'For growing businesses that need multi-branch support and advanced features.',
                'price_monthly' => 29.00,
                'price_yearly' => 290.00,
                'discount_percent_yearly' => 16.67,
                'max_branches' => 5,
                'max_users_per_branch' => 10,
                'max_devices_per_branch' => 10,
                'features' => [
                    'advanced_reporting', 'offline_mode', 'multi_branch',
                    'inventory_transfer', 'api_access', 'customer_loyalty',
                    'email_support', 'managed_backups',
                ],
                'is_active' => true,
                'is_default' => false,
                'is_popular' => true,
                'cta_text' => 'Start Free Trial',
                'highlight_color' => 'blue',
                'sort_order' => 2,
            ]
        );

        $enterprise = SubscriptionPlan::updateOrCreate(
            ['slug' => 'enterprise'],
            [
                'name' => 'Enterprise',
                'description' => 'Unlimited everything with priority support, custom integrations, and dedicated infrastructure.',
                'price_monthly' => 79.00,
                'price_yearly' => 790.00,
                'discount_percent_yearly' => 16.67,
                'max_branches' => PHP_INT_MAX,
                'max_users_per_branch' => PHP_INT_MAX,
                'max_devices_per_branch' => PHP_INT_MAX,
                'features' => [
                    'unlimited_branches', 'unlimited_users', 'unlimited_devices',
                    'all_features', 'priority_support', 'custom_integrations',
                    'white_label', 'dedicated_infrastructure', '24_7_support',
                    'sla_guarantee', 'custom_development',
                ],
                'is_active' => true,
                'is_default' => false,
                'is_popular' => false,
                'cta_text' => 'Contact Us',
                'sort_order' => 3,
            ]
        );

        // Assign features to plans via pivot
        $starterFeatures = SubscriptionFeature::whereIn('slug', [
            'full_pos', 'unlimited_transactions', 'barcode_scanning', 'offline_mode',
            'single_branch', 'basic_reporting', 'customer_management', 'role_based_access',
        ])->pluck('id')->toArray();

        $professionalFeatures = SubscriptionFeature::whereIn('slug', [
            'full_pos', 'unlimited_transactions', 'barcode_scanning', 'offline_mode',
            'multi_branch', 'inventory_management', 'inventory_transfer', 'purchase_orders',
            'advanced_reporting', 'customer_management', 'customer_loyalty', 'multi_payment',
            'promotions', 'invoicing', 'returns', 'api_access', 'role_based_access',
            'audit_trail', 'email_support', 'managed_backups',
        ])->pluck('id')->toArray();

        $enterpriseFeatures = SubscriptionFeature::whereIn('slug', [
            'full_pos', 'unlimited_transactions', 'barcode_scanning', 'offline_mode',
            'unlimited_branches', 'inventory_management', 'inventory_transfer', 'purchase_orders',
            'advanced_reporting', 'customer_management', 'customer_loyalty', 'multi_payment',
            'promotions', 'invoicing', 'returns', 'api_access', 'custom_integrations',
            'role_based_access', 'audit_trail', 'priority_support', '24_7_support',
            'managed_backups', 'dedicated_infrastructure', 'white_label',
            'sla_guarantee', 'custom_development',
        ])->pluck('id')->toArray();

        $starter->planFeatures()->sync($starterFeatures);
        $professional->planFeatures()->sync($professionalFeatures);
        $enterprise->planFeatures()->sync($enterpriseFeatures);
    }

    private function seedCurrencies(): void
    {
        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'exchange_rate_to_usd' => 1.000000, 'is_default' => true, 'is_active' => true, 'decimal_places' => 2],
            ['code' => 'UGX', 'name' => 'Ugandan Shilling', 'symbol' => 'USh', 'exchange_rate_to_usd' => 3750.000000, 'is_default' => false, 'is_active' => true, 'decimal_places' => 0],
            ['code' => 'KES', 'name' => 'Kenyan Shilling', 'symbol' => 'KSh', 'exchange_rate_to_usd' => 130.000000, 'is_default' => false, 'is_active' => true, 'decimal_places' => 2],
            ['code' => 'TZS', 'name' => 'Tanzanian Shilling', 'symbol' => 'TSh', 'exchange_rate_to_usd' => 2500.000000, 'is_default' => false, 'is_active' => true, 'decimal_places' => 0],
            ['code' => 'RWF', 'name' => 'Rwandan Franc', 'symbol' => 'FRw', 'exchange_rate_to_usd' => 1300.000000, 'is_default' => false, 'is_active' => true, 'decimal_places' => 0],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'exchange_rate_to_usd' => 0.920000, 'is_default' => false, 'is_active' => true, 'decimal_places' => 2],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'exchange_rate_to_usd' => 0.790000, 'is_default' => false, 'is_active' => true, 'decimal_places' => 2],
        ];

        foreach ($currencies as $data) {
            Currency::updateOrCreate(
                ['code' => $data['code']],
                $data
            );
        }
    }

    private function seedSettings(): void
    {
        $settings = [
            ['key' => 'platform_name', 'value' => 'ClassicPOS', 'group' => 'general', 'type' => 'string'],
            ['key' => 'platform_url', 'value' => env('APP_URL', 'https://classicpos.app'), 'group' => 'general', 'type' => 'string'],
            ['key' => 'support_email', 'value' => 'support@classicpos.app', 'group' => 'general', 'type' => 'string'],
            ['key' => 'trial_enabled', 'value' => 'true', 'group' => 'billing', 'type' => 'boolean'],
            ['key' => 'trial_days', 'value' => '14', 'group' => 'billing', 'type' => 'integer'],
            ['key' => 'default_currency', 'value' => 'KES', 'group' => 'billing', 'type' => 'string'],
            ['key' => 'maintenance_mode', 'value' => 'false', 'group' => 'system', 'type' => 'boolean'],
            ['key' => 'registration_enabled', 'value' => 'true', 'group' => 'system', 'type' => 'boolean'],
            // Agent settings
            ['key' => 'agent_default_commission_rate', 'value' => '15', 'group' => 'agents', 'type' => 'integer'],
            ['key' => 'agent_min_payout', 'value' => '10', 'group' => 'agents', 'type' => 'integer'],
            ['key' => 'agent_commission_type', 'value' => 'percentage', 'group' => 'agents', 'type' => 'string'],
            ['key' => 'agent_auto_approve_commissions', 'value' => 'false', 'group' => 'agents', 'type' => 'boolean'],
            ['key' => 'agent_commission_cleared_days', 'value' => '30', 'group' => 'agents', 'type' => 'integer'],
        ];

        foreach ($settings as $setting) {
            PlatformSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }

    private function seedAdminUser(): void
    {
        if (AdminUser::where('email', 'admin@classicpos.app')->doesntExist()) {
            AdminUser::create([
                'name' => 'Super Admin',
                'email' => 'admin@classicpos.app',
                'password' => Hash::make('@NOTcomplicated2024P@ssw0rd'),
                'role' => 'super_admin',
                'is_active' => true,
            ]);
            $this->command?->info('Admin user created: admin@classicpos.app / @NOTcomplicated2024P@ssw0rd');
        } else {
            $this->command?->warn('Admin user already exists — skipped (use tinker to reset password manually)');
        }
    }

    private function seedAgentUser(): void
    {
        if (AgentUser::where('email', 'agent@classicpos.app')->doesntExist()) {
            $agentUser = AgentUser::create([
                'name' => 'Master Agent',
                'email' => 'agent@classicpos.app',
                'password' => Hash::make('agent123'),
                'is_active' => true,
            ]);

            Agent::create([
                'user_id' => $agentUser->id,
                'code' => 'AG-MASTER',
                'name' => 'Master Agent',
                'email' => 'agent@classicpos.app',
                'commission_rate' => 15,
                'tier' => 'standard',
                'is_active' => true,
                'activated_at' => now(),
            ]);

            $this->command?->info('Agent user created: agent@classicpos.app / agent123');
        } else {
            $this->command?->info('Agent user already exists: agent@classicpos.app');
        }
    }
}
