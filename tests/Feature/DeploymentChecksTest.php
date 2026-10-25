<?php

namespace Tests\Feature;

use App\Support\DeploymentChecks;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DeploymentChecksTest extends TestCase
{
    public function test_checks_current_sqlite_installation_missing_assets_pending_migrations_and_production_settings(): void
    {
        $directory = storage_path('framework/testing/deployment-check-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($directory.'/public/build');
        touch($directory.'/database.sqlite');
        file_put_contents($directory.'/public/build/app.js', '// fixture');
        file_put_contents($directory.'/public/build/manifest.json', json_encode(['resources/js/app.js' => ['file' => 'app.js']]));
        $this->app->usePublicPath($directory.'/public');
        config(['backup.directory' => $directory.'/backups', 'database.default' => 'deployment_test', 'database.connections.deployment_test' => [
            ...config('database.connections.sqlite'), 'database' => $directory.'/database.sqlite',
        ]]);
        try {
            $this->artisan('migrate', ['--force' => true])->assertSuccessful();
            $checks = app(DeploymentChecks::class);
            $this->assertSame([], collect($checks->run())->where('ok', false)->pluck('check')->all());
            $this->artisan('deployment:check')->assertSuccessful();
            unlink($directory.'/public/build/app.js');
            $this->assertContains('Frontend assets', collect($checks->run())->where('ok', false)->pluck('check')->all());
            DB::table('migrations')->delete();
            $this->assertContains('Migrations', collect($checks->run())->where('ok', false)->pluck('check')->all());
            $this->assertSame([], collect($checks->run(false, true))->where('ok', false)->pluck('check')->all());
            config(['app.debug' => true, 'app.url' => 'http://localhost', 'session.secure' => false]);
            $failed = collect($checks->run(true, true))->where('ok', false)->pluck('check')->all();
            $this->assertContains('Debug disabled', $failed);
            $this->assertContains('HTTPS URL', $failed);
            $this->assertContains('Secure sessions', $failed);
            $this->artisan('deployment:check', ['--production' => true])->assertFailed();
        } finally {
            DB::purge('deployment_test');
            File::deleteDirectory($directory);
        }
    }
}
