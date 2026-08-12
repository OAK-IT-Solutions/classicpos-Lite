<?php

/**
 * ClassicPOS Desktop — Laravel Bootstrap Adapter
 *
 * This file is loaded by the PHP built-in server to serve the Laravel app.
 * It sets up the environment for embedded/desktop mode:
 * - SQLite database (no PostgreSQL/Redis needed)
 * - File-based cache and sessions
 * - Auto-migration on startup
 */

// Load polyfills for missing PHP extensions (mbregex, etc.)
// Use getenv() as fallback since $_SERVER may not have custom env vars in all SAPI modes
$laravelRoot = $_SERVER['LARAVEL_ROOT'] ?? getenv('LARAVEL_ROOT') ?: __DIR__;
$polyfillPath = $laravelRoot . '/bootstrap/polyfills.php';
if (file_exists($polyfillPath)) {
    require_once $polyfillPath;
}

// Set the document root
$publicPath = $_SERVER['DOCUMENT_ROOT'] ?? __DIR__;

// Check if this is a static file request (CSS, JS, images, etc.)
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH);

// Serve static files directly
$staticExtensions = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'eot'];
$ext = pathinfo($path, PATHINFO_EXTENSION);

if ($ext && in_array(strtolower($ext), $staticExtensions)) {
    $filePath = $publicPath . $path;
    if (file_exists($filePath)) {
        // Set appropriate MIME types
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
        ];
        header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
        readfile($filePath);
        return true;
    }
}

// Check if the build manifest exists (Vite compiled assets)
$buildPath = $publicPath . '/build';
if (file_exists($buildPath . '/manifest.json') && str_starts_with($path, '/build/')) {
    $filePath = $publicPath . $path;
    if (file_exists($filePath)) {
        return false; // Let PHP built-in server serve it
    }
}

// For all other requests, route through Laravel's public/index.php
require $publicPath . '/index.php';
