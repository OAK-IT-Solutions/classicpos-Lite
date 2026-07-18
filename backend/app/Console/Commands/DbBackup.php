<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class DbBackup extends Command
{
    protected $signature = 'db:backup {--keep=7 : Number of recent backups to keep}';

    protected $description = 'Dump the PostgreSQL database to a timestamped file and prune old backups';

    public function handle(): int
    {
        $disk = Storage::build(['driver' => 'local', 'root' => storage_path('app/backups')]);

        $conn = config('database.connections.pgsql');
        $filename = sprintf(
            'classicpos-%s.sql',
            now()->format('Y-m-d_H-i-s')
        );
        $path = $disk->path($filename);

        $cmd = sprintf(
            'PGPASSWORD=%s pg_dump -h %s -p %s -U %s -d %s --no-owner --no-acl -f %s 2>&1',
            escapeshellarg($conn['password']),
            escapeshellarg($conn['host']),
            escapeshellarg($conn['port']),
            escapeshellarg($conn['username']),
            escapeshellarg($conn['database']),
            escapeshellarg($path)
        );

        $this->info('Dumping database...');
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            $this->error('Backup failed: ' . implode("\n", $output));

            return Command::FAILURE;
        }

        $size = round(filesize($path) / 1024 / 1024, 2);
        $this->info("Backup saved: {$filename} ({$size} MB)");

        $keep = max(1, (int) $this->option('keep'));
        $files = collect($disk->files())
            ->filter(fn ($f) => str_starts_with($f, 'classicpos-'))
            ->sortDesc();

        if ($files->count() > $keep) {
            $files->slice($keep)->each(function ($file) use ($disk) {
                $disk->delete($file);
                $this->line("Pruned old backup: {$file}");
            });
        }

        return Command::SUCCESS;
    }
}
