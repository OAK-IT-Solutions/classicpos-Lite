<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class SeedLandlord extends Command
{
    protected $signature = 'seed:landlord {--fresh : Drop and recreate landlord database first}';
    protected $description = 'Seed the landlord database with default plans and settings';

    public function handle(): int
    {
        $dbName = config('landlord.connection') === 'landlord'
            ? config('database.connections.landlord.database')
            : 'classicpos_landlord';

        $this->info("Landlord database: {$dbName}");

        if ($this->option('fresh')) {
            $this->warn('Dropping and recreating landlord database...');
            $this->createOrDropDatabase($dbName, true);
        } else {
            $this->ensureDatabaseExists($dbName);
        }

        // Ensure landlord database exists and has tables
        $this->info('Running landlord migrations...');
        Artisan::call('migrate', [
            '--database' => 'landlord',
            '--path' => 'database/migrations/landlord',
            '--force' => true,
        ]);
        $this->line(Artisan::output());

        // Seed
        $this->info('Seeding landlord database...');
        Artisan::call('db:seed', [
            '--class' => \Database\Seeders\LandlordSeeder::class,
            '--database' => 'landlord',
            '--force' => true,
        ]);
        $this->line(Artisan::output());

        $this->info('Landlord database seeded successfully!');
        return self::SUCCESS;
    }

    private function ensureDatabaseExists(string $dbName): void
    {
        // Connect to the default 'postgres' database to check/create the landlord DB
        $pdo = $this->getPostgresConnection();
        $exists = $pdo->query("SELECT 1 FROM pg_database WHERE datname = '{$dbName}'")->fetchColumn();
        if (!$exists) {
            $this->warn("Database '{$dbName}' does not exist. Creating it...");
            $pdo->exec("CREATE DATABASE \"{$dbName}\"");
            $dbUser = config('database.connections.landlord.username');
            $pdo->exec("GRANT ALL PRIVILEGES ON DATABASE \"{$dbName}\" TO \"{$dbUser}\"");
            $pdo->exec("ALTER DATABASE \"{$dbName}\" OWNER TO \"{$dbUser}\"");
            $this->info("Database '{$dbName}' created.");
        }
    }

    private function createOrDropDatabase(string $dbName, bool $fresh): void
    {
        $pdo = $this->getPostgresConnection();
        $exists = $pdo->query("SELECT 1 FROM pg_database WHERE datname = '{$dbName}'")->fetchColumn();

        if ($exists) {
            // Terminate existing connections
            $pdo->exec(
                "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '{$dbName}' AND pid <> pg_backend_pid()"
            );
            $pdo->exec("DROP DATABASE IF EXISTS \"{$dbName}\"");
        }

        $pdo->exec("CREATE DATABASE \"{$dbName}\"");
        $dbUser = config('database.connections.landlord.username');
        $pdo->exec("GRANT ALL PRIVILEGES ON DATABASE \"{$dbName}\" TO \"{$dbUser}\"");
        $pdo->exec("ALTER DATABASE \"{$dbName}\" OWNER TO \"{$dbUser}\"");
        $this->info("Database '{$dbName}' recreated.");
    }

    private function getPostgresConnection(): \PDO
    {
        $host = config('database.connections.landlord.host');
        $port = config('database.connections.landlord.port');
        $user = config('database.connections.landlord.username');
        $pass = config('database.connections.landlord.password');
        return new \PDO("pgsql:host={$host};port={$port};dbname=postgres", $user, $pass);
    }
}
