<?php

namespace App\Console\Commands;

use App\Support\SqliteBackups;
use Illuminate\Console\Command;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'database:backup';

    protected $description = 'Create a consistent SQLite backup and verify an isolated restored copy';

    public function handle(SqliteBackups $backups): int
    {
        try {
            $path = $backups->create();
            $this->info('Backup and restore verification passed: '.$path);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Backup failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
