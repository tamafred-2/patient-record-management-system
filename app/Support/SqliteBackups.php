<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use RuntimeException;

class SqliteBackups
{
    public function directory(): string
    {
        return config('backup.directory');
    }

    public function create(): string
    {
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'sqlite' || $connection->transactionLevel() !== 0) {
            throw new RuntimeException('Backups require SQLite outside an active transaction.');
        }
        File::ensureDirectoryExists($this->directory(), 0700);
        $path = $this->directory().DIRECTORY_SEPARATOR.'rhu-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(6)).'.sqlite';
        // SQLite creates a consistent snapshot, including committed WAL data.
        $pdo = $connection->getPdo();
        $pdo->exec('VACUUM INTO '.$pdo->quote($path));
        @chmod($path, 0600);
        $this->verify($path);

        return $path;
    }

    public function verify(string $path): void
    {
        if (! is_file($path) || filesize($path) === 0) {
            throw new RuntimeException('Backup file is missing or empty.');
        }
        // Restore into an isolated temporary file; never replace the live database.
        $copy = tempnam($this->directory(), '.restore-check-');
        if ($copy === false) {
            throw new RuntimeException('Cannot create isolated restore verification file.');
        }
        $pdo = null;
        try {
            if (! copy($path, $copy) || hash_file('sha256', $path) !== hash_file('sha256', $copy)) {
                throw new RuntimeException('Restored copy does not match the backup.');
            }
            @chmod($copy, 0600);
            $pdo = new PDO('sqlite:'.$copy, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('PRAGMA query_only = ON');
            if ($pdo->query('PRAGMA integrity_check')->fetchColumn() !== 'ok'
                || $pdo->query('PRAGMA foreign_key_check')->fetch() !== false) {
                throw new RuntimeException('Restored database failed integrity or relationship checks.');
            }
            foreach (['migrations', 'users', 'patients', 'visits'] as $table) {
                $pdo->query('SELECT COUNT(*) FROM "'.$table.'"')->fetchColumn();
            }
        } finally {
            $pdo = null;
            if (is_file($copy)) {
                unlink($copy);
            }
        }
    }
}
