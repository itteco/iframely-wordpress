#!/usr/bin/env node
/**
 * Runs the PHPUnit and end-to-end suites on both ends of the supported range:
 * the minimum WordPress and PHP versions, and the latest WordPress on the newest PHP.
 *
 * The tests site is restarted and its database reset for every combination, and it
 * is returned to the versions in .wp-env.tests.json at the end. The development
 * site is not touched.
 *
 * Usage: npm run test:matrix [-- <combination> ...] [-- --keep]
 * Example: npm run test:matrix -- floor
 *
 * Without a combination, "floor" and "latest" are run. "default" stands for the
 * versions in .wp-env.tests.json, which the other test commands use. With --keep
 * the tests site stays on the last combination; CI uses that, where each job
 * runs one combination on a machine of its own.
 */
const { spawnSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..', '..');
const config = '.wp-env.tests.json';
const defaults = require(path.join(root, config));
const wpEnv = path.join(path.dirname(require.resolve('@wordpress/env/package.json')), 'bin', 'wp-env');
const playwright = path.join(path.dirname(require.resolve('@playwright/test/package.json')), 'cli.js');
const pluginDir = 'wp-content/plugins/iframely';

const COMBINATIONS = {
  // The versions in .wp-env.tests.json.
  default: {},
  // Keep in step with "Requires at least" and "Requires PHP" in iframely.php.
  floor: { core: 'WordPress/WordPress#6.6', php: '7.4' },
  // The WordPress version of .wp-env.tests.json on the newest PHP release.
  latest: { core: defaults.core, php: '8.5' },
};

const keep = process.argv.includes('--keep');
const requested = process.argv.slice(2).filter((argument) => argument !== '--keep');
const unknown = requested.filter((name) => !COMBINATIONS[name]);
if (unknown.length) {
  console.error(`Unknown combination: ${unknown.join(', ')}. Choose from: ${Object.keys(COMBINATIONS).join(', ')}.`);
  process.exit(1);
}
const names = requested.length ? requested : ['floor', 'latest'];

function run(script, args, env) {
  const result = spawnSync(process.execPath, [script, ...args], { cwd: root, env, stdio: 'inherit' });
  return result.status === 0;
}

function switchSite(env) {
  return run(wpEnv, [`--config=${config}`, 'start'], env) && run(wpEnv, [`--config=${config}`, 'reset'], env);
}

const inPlugin = (env, ...command) =>
  run(wpEnv, [`--config=${config}`, 'run', 'cli', `--env-cwd=${pluginDir}`, ...command], env);

const phpunit = (env, ...prefix) => inPlugin(env, ...prefix, 'vendor/bin/phpunit');

// PHPUnit and the other PHP tools are installed with Composer, which runs inside the site.
const phpTools = (env) =>
  fs.existsSync(path.join(root, 'vendor', 'bin', 'phpunit')) || inPlugin(env, 'composer', 'install');

const results = [];
for (const name of names) {
  const { core = defaults.core, php = defaults.phpVersion } = COMBINATIONS[name];
  const label = `${name}: ${core.replace('WordPress/WordPress#', 'WordPress ')}, PHP ${php}`;
  const env = { ...process.env, WP_ENV_CORE: core, WP_ENV_PHP_VERSION: php };
  console.log(`\n=== ${label} ===\n`);

  if (!switchSite(env)) {
    results.push({ label, suite: 'start the tests site', ok: false });
    continue;
  }
  if (!phpTools(env)) {
    results.push({ label, suite: 'install the PHP tools', ok: false });
    continue;
  }
  results.push({ label, suite: 'PHPUnit, single site', ok: phpunit(env) });
  results.push({ label, suite: 'PHPUnit, multisite', ok: phpunit(env, 'env', 'WP_MULTISITE=1') });
  results.push({ label, suite: 'end-to-end', ok: run(playwright, ['test'], env) });
}

let restored = true;
if (!keep) {
  console.log(`\n=== Back to the default tests site ===\n`);
  restored = switchSite(process.env);
}

console.log('\n=== Summary ===\n');
for (const { label, suite, ok } of results) {
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}: ${suite}`);
}
if (!restored) {
  console.log('FAIL  The tests site could not be returned to its default versions. Run "npm run env:start:tests".');
}
process.exit(restored && results.every(({ ok }) => ok) ? 0 : 1);
