<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Replaces the legacy application's Backup/Restore menu items, which invoked
 * SQL Server BACKUP DATABASE straight from a WinForms click. Here it runs on
 * a schedule and keeps a bounded number of dumps.
 */
class BackupDatabase extends Command
{
    protected $signature = 'clinic:backup {--keep=14 : How many dumps to retain}';

    protected $description = 'Write a compressed PostgreSQL dump to the backups disk';

    public function handle(): int
    {
        $config = config('database.connections.pgsql');
        $filename = sprintf('mosannen-%s.sql.gz', now()->format('Y-m-d_His'));
        $target = Storage::disk('backups')->path($filename);

        Storage::disk('backups')->makeDirectory('');

        $this->line("  در حال تهیه نسخه پشتیبان: {$filename}");

        // The password goes through the environment, never the command line,
        // so it cannot leak into the process list.
        $process = Process::fromShellCommandline(
            'pg_dump --no-owner --no-privileges --clean --if-exists '
            .'-h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d "$PGDATABASE" | gzip > "$TARGET"',
            null,
            [
                'PGHOST' => $config['host'],
                'PGPORT' => (string) $config['port'],
                'PGUSER' => $config['username'],
                'PGPASSWORD' => $config['password'],
                'PGDATABASE' => $config['database'],
                'TARGET' => $target,
            ],
            null,
            600,
        );

        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('تهیه نسخه پشتیبان ناموفق بود.');
            $this->line('  '.trim($process->getErrorOutput()));
            @unlink($target);

            return self::FAILURE;
        }

        $size = Storage::disk('backups')->size($filename);
        $this->info(sprintf('  ✓ %s (%.1f مگابایت)', $filename, $size / 1048576));

        $this->prune((int) $this->option('keep'));

        return self::SUCCESS;
    }

    private function prune(int $keep): void
    {
        $files = collect(Storage::disk('backups')->files())
            ->filter(fn ($f) => str_ends_with($f, '.sql.gz'))
            ->sortDesc()
            ->values();

        $stale = $files->slice($keep);

        foreach ($stale as $file) {
            Storage::disk('backups')->delete($file);
        }

        if ($stale->isNotEmpty()) {
            $this->line("  {$stale->count()} نسخه قدیمی حذف شد.");
        }
    }
}
