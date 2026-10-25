<?php

namespace App\Console\Commands;

use App\Support\DeploymentChecks;
use Illuminate\Console\Command;

class CheckDeployment extends Command
{
    protected $signature = 'deployment:check {--production : Also check production security settings and demo fixtures} {--before-update : Allow pending migrations and assets awaiting build}';

    protected $description = 'Check runtime, SQLite integrity, private storage and deployment configuration';

    public function handle(DeploymentChecks $checks): int
    {
        $results = $checks->run((bool) $this->option('production'), (bool) $this->option('before-update'));
        $this->table(['Check', 'Result', 'Action'], array_map(fn ($row) => [$row['check'], $row['ok'] ? 'PASS' : 'FAIL', $row['message']], $results));
        $failed = collect($results)->contains(fn ($row) => ! $row['ok']);
        $failed ? $this->error('Resolve failed checks before continuing.') : $this->info('Checks passed. Hosting, scheduler, off-device recovery and staff acceptance still require operator verification.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
