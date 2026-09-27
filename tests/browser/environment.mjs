import path from 'node:path';
export const database = path.resolve('storage/framework/testing/browser.sqlite');
export const testEnv = {
  ...process.env,
  APP_ENV: 'testing',
  APP_DEBUG: 'true',
  APP_KEY: 'base64:' + Buffer.alloc(32, 7).toString('base64'),
  APP_URL: 'http://127.0.0.1:8017',
  APP_CONFIG_CACHE: path.resolve('storage/framework/testing/config.php'),
  DB_CONNECTION: 'sqlite',
  DB_DATABASE: database,
  DB_URL: '',
  SESSION_DRIVER: 'database',
  SESSION_SECURE_COOKIE: 'false',
  CACHE_STORE: 'array',
  QUEUE_CONNECTION: 'sync',
  MAIL_MAILER: 'array',
  BCRYPT_ROUNDS: '4',
  RHU_BROWSER_TESTING: '1',
};
