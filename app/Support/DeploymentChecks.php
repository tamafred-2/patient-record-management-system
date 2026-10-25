<?php

namespace App\Support;

use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

class DeploymentChecks
{
    public function run(bool $production = false, bool $beforeUpdate = false): array
    {
        $checks = [];
        $check = function (string $name, callable $test, string $help) use (&$checks) {
            try {
                $ok = (bool) $test();
            } catch (Throwable) {
                $ok = false;
            }
            $checks[] = ['check' => $name, 'ok' => $ok, 'message' => $ok ? 'OK' : $help];
        };
        $check('PHP runtime', fn () => PHP_VERSION_ID >= 80300, 'Use PHP 8.3 or newer.');
        foreach (['pdo_sqlite', 'mbstring', 'dom', 'gd'] as $extension) {
            $check('PHP '.$extension, fn () => extension_loaded($extension), 'Enable this PHP extension in the web and CLI runtimes.');
        }
        $check('Application key', fn () => strlen(app('encrypter')->getKey()) > 0, 'Configure the existing APP_KEY; do not regenerate it during updates.');
        foreach ([storage_path(), storage_path('framework/views'), base_path('bootstrap/cache'), config('backup.directory')] as $index => $directory) {
            $check(['Storage', 'Compiled views', 'Bootstrap cache', 'Backup directory'][$index], fn () => $this->writable($directory), 'Create the private directory and grant the application account write access.');
        }
        $check('SQLite connection', fn () => DB::connection()->getDriverName() === 'sqlite', 'Use DB_CONNECTION=sqlite for this deployment.');
        $check('SQLite file', function () {
            $path = DB::connection()->getDatabaseName();
            $real = realpath($path);
            $public = realpath(public_path());

            return $real && is_file($real) && is_writable($real) && $this->writable(dirname($real))
                && (! $public || ! str_starts_with(strtolower($real), strtolower($public).DIRECTORY_SEPARATOR));
        }, 'Use a writable SQLite file and directory on local disk, outside public/.');
        $check('Database integrity', fn () => (array) DB::selectOne('PRAGMA integrity_check') === ['integrity_check' => 'ok'], 'Stop updates and investigate or restore a verified backup.');
        $check('Foreign keys', fn () => (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys === 1 && DB::select('PRAGMA foreign_key_check') === [], 'Enable foreign keys and resolve invalid record relationships.');
        if (! $beforeUpdate) {
            $check('Migrations', function () {
                $migrator = app('migrator');
                $files = array_keys($migrator->getMigrationFiles(database_path('migrations')));

                return $migrator->repositoryExists() && array_diff($files, $migrator->getRepository()->getRan()) === [];
            }, 'Back up the database and run php artisan migrate.');
            $check('Frontend assets', function () {
                $manifest = json_decode(File::get(public_path('build/manifest.json')), true, 512, JSON_THROW_ON_ERROR);
                if (empty($manifest['resources/js/app.js']['file'])) {
                    return false;
                }
                foreach ($manifest as $entry) {
                    foreach ([$entry['file'] ?? '', ...($entry['css'] ?? [])] as $file) {
                        if (! $file || ! is_file(public_path('build/'.$file))) {
                            return false;
                        }
                    }
                }

                return true;
            }, 'Run npm ci and npm run build; publish the complete public/build directory.');
        }
        if ($production) {
            $check('Production environment', fn () => app()->environment('production'), 'Set APP_ENV=production on the deployment host.');
            $check('Debug disabled', fn () => config('app.debug') === false, 'Set APP_DEBUG=false and rebuild configuration cache.');
            $check('HTTPS URL', fn () => str_starts_with(config('app.url', ''), 'https://') && ! str_contains(config('app.url', ''), '.example'), 'Set APP_URL to the actual HTTPS application URL.');
            $check('Secure sessions', fn () => config('session.secure') && config('session.encrypt'), 'Enable SESSION_SECURE_COOKIE and SESSION_ENCRYPT.');
            $check('No Vite development marker', fn () => ! is_file(public_path('hot')), 'Stop Vite and remove the stale public/hot file before serving built assets.');
            if (! $beforeUpdate) {
                $check('No active demo accounts', fn () => ! DB::table('users')->where('is_active', true)->whereIn('email', array_keys(UserSeeder::ACCOUNTS))->exists(), 'Retire public demo accounts and provision individual staff accounts.');
                $check('No synthetic patient fixtures', function () {
                    return ! DB::table('patients')->where(function ($query) {
                        foreach (['DEMO-', 'EXAMPLE-PATIENT-', 'SYNTHETIC-PATIENT-', 'HISTORY2026-PATIENT-'] as $prefix) {
                            $query->orWhere('patient_number', 'like', $prefix.'%');
                        }
                    })->exists() && ! DB::table('activity_log')->where('description', 'seed.september_clinic_completed')->exists();
                }, 'Use a production database containing only approved records; do not deploy sample datasets.');
            }
        }

        return $checks;
    }

    private function writable(string $directory): bool
    {
        if (! is_dir($directory)) {
            File::ensureDirectoryExists($directory, 0700);
        }
        $path = $directory.DIRECTORY_SEPARATOR.'.rhu-write-check-'.bin2hex(random_bytes(6));
        try {
            return file_put_contents($path, 'check', LOCK_EX) === 5;
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
