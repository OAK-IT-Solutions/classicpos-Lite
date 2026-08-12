<?php

/**
 * ClassicPOS Desktop — Startup Script
 *
 * Lightweight first-run initialization only.
 * Runs BEFORE the PHP server starts.
 * Handles: directory creation, .env generation, APP_KEY generation.
 *
 * Heavy operations (migrations, cache clear) are handled by
 * StartupMiddleware inside Laravel — NOT here.
 */

$laravelRoot = $_SERVER['LARAVEL_ROOT'] ?? dirname(__DIR__);

// Load polyfills
$polyfillPath = $laravelRoot . '/bootstrap/polyfills.php';
if (file_exists($polyfillPath)) {
    require_once $polyfillPath;
}

$appDir = $_SERVER['APP_DATA_DIR'] ?? dirname(__DIR__);
$dbPath = $appDir . '/data/classicpos.sqlite';

echo "[ClassicPOS] Laravel root: {$laravelRoot}\n";
echo "[ClassicPOS] App data dir: {$appDir}\n";

// Ensure data directory exists
if (!is_dir($appDir . '/data')) {
    mkdir($appDir . '/data', 0755, true);
}

// Ensure storage directories
$storageDirs = [
    $appDir . '/storage/app',
    $appDir . '/storage/framework/cache',
    $appDir . '/storage/framework/sessions',
    $appDir . '/storage/framework/views',
    $appDir . '/storage/logs',
];

foreach ($storageDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Ensure bootstrap/cache is writable
$bootstrapCache = $laravelRoot . '/bootstrap/cache';
if (!is_dir($bootstrapCache)) {
    mkdir($bootstrapCache, 0755, true);
}

// Generate minimal .env if missing
$envPath = $laravelRoot . '/.env';
if (!file_exists($envPath)) {
    echo "[ClassicPOS] Creating .env file...\n";
    $envContent = "APP_NAME=ClassicPOS\nAPP_ENV=production\nAPP_DEBUG=false\n";
    $envContent .= "APP_URL=http://127.0.0.1\n";
    $envContent .= "APP_KEY=\n";
    $envContent .= "DB_CONNECTION=sqlite\nDB_DATABASE={$dbPath}\n";
    $envContent .= "CACHE_STORE=file\nSESSION_DRIVER=file\nQUEUE_CONNECTION=sync\n";
    $envContent .= "CLASSICPOS_SELF_HOSTED=true\nSANCTUM_STATEFUL_DOMAINS=localhost\n";
    $envContent .= "CORS_ALLOWED_ORIGINS=*\n";
    file_put_contents($envPath, $envContent);
    echo "[ClassicPOS] .env created.\n";
}

echo "[ClassicPOS] Startup complete (lightweight).\n";
