import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { database, testEnv } from './environment.mjs';
export default function setup() {
  if (database !== path.resolve('storage/framework/testing/browser.sqlite')) throw new Error('Unsafe test database path');
  fs.mkdirSync(path.dirname(database), { recursive: true });
  if (!fs.existsSync(database)) fs.writeFileSync(database, '');
  for (const args of [['artisan','migrate:fresh','--force','--no-interaction'],['artisan','db:seed','--class=WorkflowDemoSeeder','--no-interaction']]) {
    execFileSync('php', args, { env: testEnv, stdio: 'pipe' });
  }
}
setup();
