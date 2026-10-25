<?php

namespace Tests\Feature;

use App\Support\SqliteBackups;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use Tests\TestCase;

class SqliteBackupTest extends TestCase
{
    public function test_snapshot_can_be_restored_without_touching_source_and_corruption_is_rejected(): void
    {
        $directory = storage_path('framework/testing/backup-test-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($directory);
        $source = $directory.'/source.sqlite';
        touch($source);
        config(['backup.directory' => $directory, 'database.default' => 'backup_test', 'database.connections.backup_test' => [
            ...config('database.connections.sqlite'), 'database' => $source,
        ]]);
        try {
            $pdo = DB::connection()->getPdo();
            $pdo->exec('PRAGMA journal_mode=WAL');
            foreach (['migrations', 'users', 'patients', 'visits'] as $table) {
                $pdo->exec('CREATE TABLE '.$table.' (id INTEGER PRIMARY KEY)');
                $pdo->exec('INSERT INTO '.$table.' (id) VALUES (1)');
            }
            $backups = app(SqliteBackups::class);
            $path = $backups->create();
            $restored = new PDO('sqlite:'.$path);
            $this->assertSame(1, (int) $restored->query('SELECT COUNT(*) FROM patients')->fetchColumn());
            $restored = null;
            $this->artisan('database:verify-backup', ['filename' => basename($path)])->assertSuccessful();
            $this->assertSame(1, DB::table('patients')->count());
            file_put_contents($path, 'corrupt');
            $this->artisan('database:verify-backup', ['filename' => basename($path)])->assertFailed();
            $this->assertSame([], glob($directory.'/.restore-check-*'));
            $this->assertSame(1, DB::table('patients')->count());
            $this->artisan('database:verify-backup', ['filename' => '../source.sqlite'])->assertFailed();
        } finally {
            $pdo = null;
            DB::purge('backup_test');
            File::deleteDirectory($directory);
        }
    }
}
