import { execFileSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { describe, expect, it } from 'vitest';
import { parseDistignore, packaged } from '../bin/lib/distignore';

const root = path.resolve(__dirname, '..', '..');

describe('parseDistignore', () => {
  const excluded = parseDistignore(
    ['# A comment', '', '/tests', '/.env', '/.env.*', '/lang/*.po', '/README.md', 'Thumbs.db', '/svn/'].join('\n'),
  );
  const kept = (file) => packaged([file], excluded).length === 1;

  it('leaves out a listed directory with everything in it', () => {
    expect(kept('tests/php/PluginTest.php')).toBe(false);
    expect(kept('svn/assets/icon.svg')).toBe(false);
  });

  it('leaves out a listed file, but not a file that only starts with its name', () => {
    expect(kept('README.md')).toBe(false);
    expect(kept('.env')).toBe(false);
    expect(kept('readme.txt')).toBe(true);
    expect(kept('README.md.txt')).toBe(true);
  });

  it('keeps a path from the root apart from the same name deeper down', () => {
    expect(kept('app/tests/Helper.php')).toBe(true);
    expect(kept('docs/README.md')).toBe(true);
  });

  it('lets "*" stand for a part of one name only', () => {
    expect(kept('.env.local')).toBe(false);
    expect(kept('lang/iframely-ru_RU.po')).toBe(false);
    expect(kept('lang/iframely.pot')).toBe(true);
    expect(kept('lang/sub/iframely-ru_RU.po')).toBe(true);
  });

  it('matches an entry without a leading slash at any depth', () => {
    expect(kept('Thumbs.db')).toBe(false);
    expect(kept('assets/Thumbs.db')).toBe(false);
  });

  it('takes the dots of a name literally', () => {
    expect(kept('READMEXmd')).toBe(true);
  });
});

describe('the plugin package', () => {
  const tracked = execFileSync('git', ['ls-files', '-z'], { cwd: root, encoding: 'utf8' }).split('\0').filter(Boolean);
  const files = packaged(tracked, parseDistignore(fs.readFileSync(path.join(root, '.distignore'), 'utf8')));

  it('holds what the plugin needs to run', () => {
    for (const file of [
      'iframely.php',
      'index.php',
      'uninstall.php',
      'readme.txt',
      'LICENSE',
      'app/Plugin.php',
      'views/settings.php',
      'build/index.js',
      'build/index.asset.php',
      'build/index.css',
      'build/admin.css',
      'assets/onboarding.png',
      'lang/iframely.pot',
    ]) {
      expect(files).toContain(file);
    }
  });

  it('holds nothing else', () => {
    const places = new Set(files.map((file) => (file.includes('/') ? file.split('/')[0] : file)));

    expect([...places].sort()).toEqual(
      [
        'LICENSE',
        'app',
        'assets',
        'build',
        'iframely.php',
        'index.php',
        'lang',
        'readme.txt',
        'uninstall.php',
        'views',
      ].sort(),
    );
    expect(files.filter((file) => file.startsWith('lang/'))).toEqual(['lang/iframely.pot']);
    expect(files.filter((file) => path.basename(file).startsWith('.'))).toEqual([]);
  });
});
