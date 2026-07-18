<?php

/**
 * ClassicPOS Desktop — Startup Script
 *
 * Run by Tauri on app startup (before PHP built-in server).
 * Handles:
 * - First-run migration
 * - Version-based migration (when app updates)
 * - Storage permissions
 * - Cache clearing
 */

$appDir = $_SERVER['APP_DATA_DIR'] ?? dirname(__DIR__);
$dbPath = $appDir . '/data/classicpos.sqlite';
$versionFile = $appDir . '/data/.app_version';
$currentVersion = $_SERVER['APP_VERSION'] ?? '1.0.0';

// Ensure data directory exists
if (!is_dir($appDir . '/data')) {
    mkdir($appDir . '/data', 0755, true);
}

// Check if this is a first run or version update
$lastVersion = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : null;

if ($lastVersion !== $currentVersion) {
    echo "[ClassicPOS] Version changed: {$lastVersion} -> {$currentVersion}\n";

    // Run migration
    $artisan = dirname(__DIR__) . '/artisan';

    if (file_exists($artisan)) {
        echo "[ClassicPOS] Running database migration...\n";

        // Set environment
        putenv("APP_ENV=production");
        putenv("APP_DEBUG=false");
        putenv("DB_CONNECTION=sqlite");
        putenv("DB_DATABASE={$dbPath}");
        putenv("CACHE_STORE=file");
        putenv("SESSION_DRIVER=file");
        putenv("QUEUE_CONNECTION=sync");

        // Run artisan migrate
        $cmd = "php " . escapeshellarg($artisan) . " migrate --force 2>&1";
        $output = shell_exec($cmd);
        echo $output;

        // Run offline seeder if first run
        if ($lastVersion === null && !file_exists($dbPath . '-seeded')) {
            echo "[ClassicPOS] First run — seeding database...\n";
            $cmd = "php " . escapeshellarg($artisan) . " db:seed --class=Database\\Seeders\\OfflineDatabaseSeeder --force 2>&1";
            $output = shell_exec($cmd);
            echo $output;
            touch($dbPath . '-seeded');
        }

        // Write version file
        file_put_contents($versionFile, $currentVersion);
        echo "[ClassicPOS] Migration complete.\n";
    } else {
        echo "[ClassicPOS] Warning: artisan not found at {$artisan}\n";
    }
} else {
    echo "[ClassicPOS] Version {$currentVersion} — no migration needed.\n";
}

// Ensure storage permissions
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

// Clear cache on startup
$artisan = dirname(__DIR__) . '/artisan';
if (file_exists($artisan)) {
    putenv("APP_ENV=production");
    putenv("DB_CONNECTION=sqlite");
    putenv("DB_DATABASE={$dbPath}");
    shell_exec("php " . escapeshellarg($artisan) . " config:clear 2>&1");
    shell_exec("php " . escapeshellarg($artisan) . " cache:clear 2>&1");
    echo "[ClassicPOS] Cache cleared.\n";
}

echo "[ClassicPOS] Startup complete.\n";
