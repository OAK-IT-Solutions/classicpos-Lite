<?php

return [
    'default' => env('DB_CONNECTION', 'pgsql'),
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
        ],
        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5444'),
            'database' => env('DB_DATABASE', 'classicpos'),
            'username' => env('DB_USERNAME', 'classicpos'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],
        'landlord' => [
            'driver' => 'pgsql',
            'url' => env('LANDLORD_DB_URL'),
            'host' => env('LANDLORD_DB_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('LANDLORD_DB_PORT', env('DB_PORT', '5444')),
            'database' => env('LANDLORD_DB_DATABASE', 'classicpos_landlord'),
            'username' => env('LANDLORD_DB_USERNAME', env('DB_USERNAME', 'classicpos')),
            'password' => env('LANDLORD_DB_PASSWORD', env('DB_PASSWORD', '')),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],
    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_run' => true,
    ],
];
