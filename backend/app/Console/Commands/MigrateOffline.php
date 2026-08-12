<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class MigrateOffline extends Command
{
    protected $signature = 'migrate:offline
        {--fresh : Drop all tables and re-migrate}
        {--seed : Seed the database after migration}
        {--path= : Path to migration files relative to database/migrations}';

    protected $description = 'Run migrations for offline/SQLite mode (skips PostgreSQL-specific migrations)';

    public function handle(): int
    {
        $driver = config('database.default');

        if ($driver !== 'sqlite') {
            $this->error("This command is intended for SQLite mode. Current driver: {$driver}");
            $this->error("Set DB_CONNECTION=sqlite in your .env file to use offline mode.");
            return self::FAILURE;
        }

        $dbPath = config('database.connections.sqlite.database');

        // Ensure the data directory exists
        $dbDir = dirname($dbPath);
        if (!File::isDirectory($dbDir)) {
            File::makeDirectory($dbDir, 0755, true);
            $this->info("Created data directory: {$dbDir}");
        }

        // Ensure the database file exists
        if (!File::exists($dbPath)) {
            File::put($dbPath, '');
            $this->info("Created SQLite database: {$dbPath}");
        }

        // Enable WAL mode for better concurrent read/write
        DB::statement('PRAGMA journal_mode=WAL');
        DB::statement('PRAGMA busy_timeout=5000');
        DB::statement('PRAGMA foreign_keys=ON');
        $this->info("SQLite WAL mode enabled.");

        if ($this->option('fresh')) {
            $this->info("Dropping all tables...");
            Artisan::call('migrate:fresh', [
                '--database' => 'sqlite',
                '--path' => $this->option('path'),
            ]);
            $this->info(Artisan::output());
        } else {
            $this->info("Running migrations...");
            Artisan::call('migrate', [
                '--database' => 'sqlite',
                '--path' => $this->option('path'),
                '--force' => true,
            ]);
            $this->info(Artisan::output());
        }

        if ($this->option('seed')) {
            $this->info("Seeding database (offline mode)...");
            Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\OfflineDatabaseSeeder', '--force' => true]);
            $this->info(Artisan::output());
        }

        $this->info("Offline migration complete!");
        $this->newLine();
        $this->info("Database: {$dbPath}");
        $this->info("Size: " . round(filesize($dbPath) / 1024, 2) . " KB");
        $this->info("Mode: SQLite (offline)");
        $this->info("Cache: file");
        $this->info("Session: file");
        $this->info("Queue: sync");

        return self::SUCCESS;
    }
}
