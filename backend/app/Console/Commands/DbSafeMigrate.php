<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DbSafeMigrate extends Command
{
    protected $signature = 'db:safe-migrate
        {--landlord : Also run landlord migrations}
        {--force : Skip confirmation prompt}';

    protected $description = 'Run migrations with pre-backup and post-verification safety checks';

    public function handle(): int
    {
        if (!$this->option('force')) {
            $this->alert('This will backup the database, run migrations, and verify integrity.');
            $this->warn('If verification fails, the database will be restored from backup.');
            if (!$this->confirm('Continue?', true)) {
                return Command::FAILURE;
            }
        }

        // ---- 1. Pre-migration backup ----
        $this->info('[1/4] Creating pre-migration backup...');
        $backupDir = storage_path('app/backups/pre-migration');
        File::ensureDirectoryExists($backupDir);

        $timestamp = now()->format('Y-m-d_H-i-s');
        $mainBackup = "{$backupDir}/classicpos-{$timestamp}.sql";
        $landlordBackup = "{$backupDir}/classicpos_landlord-{$timestamp}.sql";

        $conn = config('database.connections.pgsql');
        $cmd = sprintf(
            'PGPASSWORD=%s pg_dump -h %s -p %s -U %s -d %s --no-owner --no-acl -f %s 2>&1',
            escapeshellarg($conn['password']),
            escapeshellarg($conn['host']),
            escapeshellarg($conn['port']),
            escapeshellarg($conn['username']),
            escapeshellarg($conn['database']),
            escapeshellarg($mainBackup)
        );

        $this->line('  Dumping main database...');
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            $this->error('  Backup failed! Aborting. Output: ' . implode("\n", $output));
            return Command::FAILURE;
        }

        $size = round(filesize($mainBackup) / 1024 / 1024, 2);
        $this->line("  Main backup saved: {$mainBackup} ({$size} MB)");

        if ($this->option('landlord')) {
            $landlordConn = config('database.connections.landlord');
            $landlordCmd = sprintf(
                'PGPASSWORD=%s pg_dump -h %s -p %s -U %s -d %s --no-owner --no-acl -f %s 2>&1',
                escapeshellarg($landlordConn['password']),
                escapeshellarg($landlordConn['host']),
                escapeshellarg($landlordConn['port']),
                escapeshellarg($landlordConn['username']),
                escapeshellarg($landlordConn['database']),
                escapeshellarg($landlordBackup)
            );
            exec($landlordCmd, $landlordOutput, $landlordExitCode);
            if ($landlordExitCode === 0) {
                $this->line("  Landlord backup saved: {$landlordBackup}");
            } else {
                $this->warn('  Landlord backup skipped — database may not exist');
            }
        }

        // ---- 2. Run migrations ----
        $this->info('[2/4] Running migrations...');
        $exitCode = $this->call('migrate', ['--force' => true]);
        if ($exitCode !== 0) {
            $this->error('  Migration failed!');
            return Command::FAILURE;
        }

        if ($this->option('landlord')) {
            $this->call('migrate', [
                '--path' => 'database/migrations/landlord',
                '--database' => 'landlord',
                '--force' => true,
            ]);
        }

        // ---- 3. Verify integrity ----
        $this->info('[3/4] Verifying database integrity...');
        $verifyExitCode = $this->call('db:verify');

        if ($verifyExitCode !== 0) {
            $this->error('  Verification FAILED! Restoring from backup...');
            $restoreCmd = sprintf(
                'PGPASSWORD=%s psql -h %s -p %s -U %s -d %s -f %s 2>&1',
                escapeshellarg($conn['password']),
                escapeshellarg($conn['host']),
                escapeshellarg($conn['port']),
                escapeshellarg($conn['username']),
                escapeshellarg($conn['database']),
                escapeshellarg($mainBackup)
            );
            exec($restoreCmd, $restoreOutput, $restoreExitCode);
            if ($restoreExitCode === 0) {
                $this->info('  Database restored from pre-migration backup.');
            } else {
                $this->error('  RESTORE ALSO FAILED! Manual recovery required.');
                $this->line("  Backup file: {$mainBackup}");
            }
            return Command::FAILURE;
        }

        // ---- 4. Cleanup old backups ----
        $this->info('[4/4] Cleaning up old backups...');
        $this->call('db:backup', ['--keep' => 7]);

        $this->info('Migration completed successfully with verified integrity.');
        return Command::SUCCESS;
    }
}
