#!/usr/bin/env node
/**
 * Puts together what is released to wordpress.org, in the layout of its SVN repository:
 *
 *   release/trunk    the plugin package
 *   release/assets   banners, icons and screenshots of the plugin page (from svn/assets)
 *   release/tag/x    a copy of trunk for the version, with --tag=x
 *
 * The package is every file under version control that .distignore does not list.
 * A file that only exists on this machine, such as .env, can never get into it.
 *
 * Usage: npm run release [-- --tag=<version>] [-- --allow-dirty]
 */
const { execFileSync, spawnSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const { parseDistignore, packaged } = require('./lib/distignore');

const root = path.resolve(__dirname, '..');
const release = path.join(root, 'release');

function fail(message) {
  console.error(`\n${message}`);
  process.exit(1);
}

const options = { tag: '', allowDirty: false };
for (const argument of process.argv.slice(2)) {
  const tag = argument.match(/^--tag=(.+)$/);
  if (tag) {
    options.tag = tag[1];
  } else if (argument === '--allow-dirty') {
    options.allowDirty = true;
  } else {
    fail(`Unknown argument: ${argument}\nUsage: npm run release [-- --tag=<version>] [-- --allow-dirty]`);
  }
}

const git = (...args) => execFileSync('git', args, { cwd: root, encoding: 'utf8' });

// The scripts and styles in build/ are part of the package, so they have to be current.
const npm = process.platform === 'win32' ? 'npm.cmd' : 'npm';
if (spawnSync(npm, ['run', 'build'], { cwd: root, stdio: 'inherit' }).status !== 0) {
  fail('The build failed.');
}

const changed = git('status', '--porcelain', '--untracked-files=no').trim();
if (changed && !options.allowDirty) {
  fail(
    `These files differ from what is committed:\n${changed}\n\n` +
      'A release is made from committed files. Commit the changes (after a build, build/ is one of them),\n' +
      'or pass --allow-dirty to package the files as they are now.',
  );
}

const header = fs.readFileSync(path.join(root, 'iframely.php'), 'utf8').match(/^\s*\*\s*Version:\s*(\S+)/m);
const version = header ? header[1] : '';
if (options.tag && options.tag !== version) {
  fail(`The tag ${options.tag} is not the version of the plugin (${version || 'unknown'}, see iframely.php).`);
}

const tracked = git('ls-files', '-z').split('\0').filter(Boolean);
const excluded = parseDistignore(fs.readFileSync(path.join(root, '.distignore'), 'utf8'));
const files = packaged(tracked, excluded).filter((file) => fs.existsSync(path.join(root, file)));
const assets = tracked.filter((file) => /^svn\/assets\/[^/]+$/.test(file));

function copy(file, from, to) {
  const target = path.join(to, file);
  fs.mkdirSync(path.dirname(target), { recursive: true });
  fs.copyFileSync(path.join(from, file), target);
}

fs.rmSync(release, { recursive: true, force: true });
for (const file of files) {
  copy(file, root, path.join(release, 'trunk'));
  if (options.tag) {
    copy(file, root, path.join(release, 'tag', options.tag));
  }
}
for (const file of assets) {
  copy(path.basename(file), path.join(root, 'svn', 'assets'), path.join(release, 'assets'));
}

console.log(`\nIframely ${version}: ${files.length} files in release/trunk, ${assets.length} in release/assets.`);
if (options.tag) {
  console.log(`A copy of the package is in release/tag/${options.tag}.`);
}
