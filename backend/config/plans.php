<?php

return [
    'starter' => [
        'name' => 'Starter',
        'max_branches' => 1,
        'max_users_per_branch' => 3,
        'max_devices_per_branch' => 2,
        'features' => [
            'basic_reporting',
            'offline_mode',
            'single_branch',
        ],
    ],
    'standard' => [
        'name' => 'Professional',
        'max_branches' => 5,
        'max_users_per_branch' => 10,
        'max_devices_per_branch' => 10,
        'features' => [
            'advanced_reporting',
            'offline_mode',
            'multi_branch',
            'inventory_transfer',
            'api_access',
            'customer_loyalty',
        ],
    ],
    'premium' => [
        'name' => 'Enterprise',
        'max_branches' => PHP_INT_MAX,
        'max_users_per_branch' => PHP_INT_MAX,
        'max_devices_per_branch' => PHP_INT_MAX,
        'features' => [
            'unlimited_branches',
            'unlimited_users',
            'unlimited_devices',
            'all_features',
            'priority_support',
            'custom_integrations',
            'white_label',
            'dedicated_infrastructure',
        ],
    ],
];
