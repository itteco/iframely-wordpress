#!/usr/bin/env node
/**
 * Runs the official Plugin Check tool (https://wordpress.org/plugins/plugin-check/)
 * against the plugin inside the wp-env tests site.
 *
 * Only what ships to wordpress.org is checked: everything listed in .distignore
 * is excluded, so that file stays the single list of development-only paths.
 *
 * Ends with an error when Plugin Check reports errors; warnings are shown only.
 *
 * Usage: npm run lint:plugin-check [-- <extra "wp plugin check" arguments>]
 * Example: npm run lint:plugin-check -- --format=json
 */
const { spawnSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const config = '.wp-env.tests.json';

const directories = [];
const files = [];
for (const line of fs.readFileSync(path.join(root, '.distignore'), 'utf8').split('\n')) {
  const entry = line.trim().replace(/^\//, '');
  if (!entry || entry.startsWith('#') || entry.includes('*')) {
    continue;
  }
  const target = path.join(root, entry);
  if (fs.existsSync(target) && fs.statSync(target).isDirectory()) {
    directories.push(entry);
  } else {
    files.push(entry);
  }
}

const quote = (value) => `'${String(value).replace(/'/g, `'\\''`)}'`;
const check = [
  'wp plugin check iframely',
  `--exclude-directories=${quote(directories.join(','))}`,
  `--exclude-files=${quote(files.join(','))}`,
  ...process.argv.slice(2).map(quote),
].join(' ');

// Plugin Check is only active while it runs, so it cannot affect other tests.
const script = [
  'wp plugin is-installed plugin-check || wp plugin install plugin-check --quiet',
  'wp plugin activate plugin-check --quiet',
  `${check}; status=$?`,
  'wp plugin deactivate plugin-check --quiet',
  'exit $status',
].join('\n');

const wpEnv = path.join(path.dirname(require.resolve('@wordpress/env/package.json')), 'bin', 'wp-env');
const result = spawnSync(process.execPath, [wpEnv, `--config=${config}`, 'run', 'cli', 'sh', '-c', script], {
  cwd: root,
  encoding: 'utf8',
  stdio: ['inherit', 'pipe', 'inherit'],
});
process.stdout.write(result.stdout || '');

if (result.status !== 0) {
  process.exit(result.status === null ? 1 : result.status);
}

// "wp plugin check" reports what it finds and ends without an error. Errors fail this
// script; warnings, which include advice on the wording of readme.txt, do not.
const errors = (result.stdout || '').split('\n').filter((line) => /(^|\t)ERROR(\t|$)/.test(line)).length;
if (errors) {
  console.error(`\nPlugin Check found ${errors} error${errors === 1 ? '' : 's'}.`);
  process.exit(1);
}
