#!/usr/bin/env node
/**
 * Runs the opt-in live tests (PHPUnit group "live") against the real Iframely API.
 *
 * The API key is read from the IFRAMELY_API_KEY environment variable or, failing
 * that, from a gitignored .env file in the project root. It is handed to the
 * container through the process environment and never appears on a command line,
 * so it does not end up in terminal output, shell history or CI logs.
 *
 * Usage: npm run test:php:live [-- <extra phpunit arguments>]
 */
const { execFileSync, spawnSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..', '..');
const config = '.wp-env.tests.json';
const pluginDir = '/var/www/html/wp-content/plugins/iframely';

function readKey() {
  if (process.env.IFRAMELY_API_KEY) {
    return process.env.IFRAMELY_API_KEY.trim();
  }
  const envFile = path.join(root, '.env');
  if (fs.existsSync(envFile)) {
    const match = fs.readFileSync(envFile, 'utf8').match(/^\s*IFRAMELY_API_KEY\s*=\s*(.*?)\s*$/m);
    if (match) {
      return match[1].replace(/^(['"])(.*)\1$/, '$2');
    }
  }
  return '';
}

const key = readKey();
if (!key) {
  console.log('Skipping the live tests: set IFRAMELY_API_KEY (in your shell or in a .env file) to run them.');
  process.exit(0);
}

let status;
try {
  const wpEnv = path.join(path.dirname(require.resolve('@wordpress/env/package.json')), 'bin', 'wp-env');
  const output = execFileSync(process.execPath, [wpEnv, `--config=${config}`, 'status', '--json'], {
    cwd: root,
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'ignore'],
  });
  status = JSON.parse(output);
} catch {
  status = null;
}

if (!status || status.status !== 'running' || !status.installPath) {
  console.error('The tests environment is not running. Start it with: npm run env:start:tests');
  process.exit(1);
}

const user = typeof process.getuid === 'function' ? ['--user', `${process.getuid()}:${process.getgid()}`] : [];
const result = spawnSync(
  'docker',
  [
    'compose',
    '-f',
    path.join(status.installPath, 'docker-compose.yml'),
    'exec',
    '-T',
    // Name only: Docker copies the value from this process's environment.
    '-e',
    'IFRAMELY_API_KEY',
    '-w',
    pluginDir,
    ...user,
    'cli',
    'vendor/bin/phpunit',
    '--group',
    'live',
    ...process.argv.slice(2),
  ],
  { stdio: 'inherit', env: { ...process.env, IFRAMELY_API_KEY: key } },
);

process.exit(result.status === null ? 1 : result.status);
