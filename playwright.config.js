import { defineConfig } from '@playwright/test';
import { testEnv } from './tests/browser/environment.mjs';
export default defineConfig({
  testDir: './tests/browser',
  workers: 1,
  timeout: 45000,
  use: { baseURL: 'http://127.0.0.1:8017', headless: true, channel: process.platform === 'win32' ? 'msedge' : undefined, viewport: { width: 1440, height: 1000 }, screenshot: 'only-on-failure', trace: 'retain-on-failure' },
  outputDir: 'storage/framework/testing/browser-results',
  reporter: 'list',
  webServer: { command: 'node tests/browser/setup.mjs && php -S 127.0.0.1:8017 -t public scripts/browser-router.php', url: 'http://127.0.0.1:8017/login', env: testEnv, reuseExistingServer: false, timeout: 60000 }
});
