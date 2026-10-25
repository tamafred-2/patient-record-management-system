<?php

namespace App\Console\Commands;

use App\Support\SqliteBackups;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class VerifyDatabaseBackup extends Command
{
    protected $signature = 'database:verify-backup {filename : Backup filename inside the configured backup directory}';

    protected $description = 'Restore a backup into an isolated copy and check integrity without changing the live database';

    public function handle(SqliteBackups $backups): int
    {
        try {
            $name = $this->argument('filename');
            if (! preg_match('/\Arhu-[0-9]{8}-[0-9]{6}-[a-f0-9]{12}\.sqlite\z/', $name)) {
                throw new RuntimeException('Supply a filename produced by database:backup.');
            }
            $backups->verify($backups->directory().DIRECTORY_SEPARATOR.$name);
            $this->info('Isolated restore verification passed. Live database unchanged.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Verification failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
