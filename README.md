# Iframely WordPress plugin

## Requirements

- Node.js 22.22.2 or newer on the 22 line, 24.15 or newer on the 24 line, or 26 and later, with npm
- Docker (Docker Engine with the Compose plugin is enough; Docker Desktop is not required)

PHP, Composer, MySQL, WP-CLI and PHPUnit are not needed on your machine. They run inside the
[`wp-env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) containers.

## Installation

    npm install
    npm run env:start
    npm run composer install

`npm run env:start` starts two independent WordPress sites with the plugin mounted and activated:

| Site  | URL                   | Config file          | Purpose                               |
|-------|-----------------------|----------------------|---------------------------------------|
| dev   | http://localhost:8888 | `.wp-env.json`       | Manual development and testing        |
| tests | http://localhost:8889 | `.wp-env.tests.json` | PHPUnit, end-to-end tests and linters |

Log in at `/wp-admin` with `admin` / `password`. Both sites pin the WordPress and PHP versions in their config
file. To try other versions, set `WP_ENV_CORE` and `WP_ENV_PHP_VERSION` and restart, for example:

    WP_ENV_CORE=WordPress/WordPress#6.6 WP_ENV_PHP_VERSION=7.4 npx wp-env --config=.wp-env.tests.json start

The plugin supports WordPress 6.6 and later on PHP 7.4 and later. `npm run test:matrix` runs the tests on both ends
of that range (see [Tests](#tests)).

Other environment commands:

    npm run env:stop       # stop both sites
    npm run env:reset      # reset both databases
    npm run env:destroy    # remove containers, volumes and downloaded files
    npm run wp -- <cmd>    # WP-CLI on the dev site, e.g. npm run wp -- plugin list
    npm run wp:tests -- <cmd>

### Gutenberg build
    npm run build

### Gutenberg watch
    npm run start

## Tests

    npm test                      # everything below except the live test
    npm run test:js               # Vitest unit tests (tests/js)
    npm run test:php              # PHPUnit integration tests (tests/php), single site
    npm run test:php:multisite    # the same tests on multisite
    npm run test:e2e              # Playwright end-to-end tests (tests/e2e) against the tests site

Arguments after `--` are passed through, e.g. `npm run test:php -- --filter SettingsTest` or
`npm run test:js -- --coverage`.

The PHPUnit and end-to-end tests run on the WordPress and PHP versions of the tests site. To run them on the minimum
supported versions (WordPress 6.6, PHP 7.4) and on the latest WordPress with the newest PHP in one go:

    npm run test:matrix             # both combinations
    npm run test:matrix -- floor    # only the minimum versions ("latest" for the other one)
    npm run test:matrix -- default  # the versions of the tests site itself

This restarts the tests site for each combination, resets its database, and returns it to its default versions at
the end (not with `-- --keep`, which CI uses). The versions are listed at the top of `tests/bin/matrix.js`.

None of these tests talks to the real Iframely API:

- PHPUnit intercepts every HTTP request (`tests/php/TestCase.php`); a request without a declared response fails the test.
- The tests site loads `tests/e2e/mu-plugins/iframely-api-stub.php`, which answers API calls locally. It accepts any
  API key starting with `e2evalid`. The two scripts the editor loads from `if-cdn.com` are replaced in the browser.
- The stub answers the way the real API does: a player with player options ("Closed captions", "Start from") for
  YouTube and Vimeo URLs, and a summary card with the "Make it a small card" option for any other page. The formats
  were taken from the real API, and the live test below checks that they still hold. A test can make the next API
  call slow or make it fail (`delayNextApiRequest`, `failNextApiRequests` in `tests/e2e/fixtures/iframely.js`).
- PHPUnit uses its own database tables (prefix `wptests_`), so it does not touch the content of the tests site.

Tests named `known_issue` / `known issue:` record current behaviour that is considered a defect. They are expected
to be inverted when the defect is fixed.

The end-to-end tests need a browser once per machine (this installs system packages with `sudo`):

    npm run test:e2e:install

### Live test

One small test class (`tests/php/LiveApiTest.php`) calls the real API to check that the mocks still match it. It is
opt-in and needs an API key, either in your shell or in a gitignored `.env` file in the project root:

    IFRAMELY_API_KEY=... npm run test:php:live

Without a key the command does nothing. The key is passed to the container through the environment and is never
printed.

## Linting

    npm run lint            # everything below
    npm run lint:js         # ESLint (@wordpress/eslint-plugin)
    npm run lint:css        # stylelint
    npm run lint:php        # PHP_CodeSniffer: PSR-12, WordPress security/i18n rules, PHPCompatibilityWP
    npm run lint:phpstan    # PHPStan with the WordPress extension

The official [Plugin Check](https://wordpress.org/plugins/plugin-check/) tool is not part of `npm run lint`, because
it installs a plugin into the tests site on first use:

    npm run lint:plugin-check    # checks what ships to wordpress.org (everything not in .distignore)

It fails on errors; warnings are shown only.

### Update POT file
    wp i18n make-pot . lang/iframely.pot --exclude=tests,vendor,node_modules,svn,artifacts,release --headers="{\"Report-Msgid-Bugs-To\": \"https://wordpress.org/support/plugin/iframely/\", \"Last-Translator\": \"Iframely.com\", \"Language-Team\": \"Iframely.com\"}"
    wp i18n make-json lang --no-purge

## Continuous integration

`.github/workflows/ci.yml` runs on every pull request and on every push to `main`:

- **Linters, unit tests, package**: Prettier, ESLint, stylelint, the unit tests, PHP_CodeSniffer, PHPStan, Plugin
  Check, and `npm run release`. The package is kept with the run as the `iframely` artifact.
- **Tests**: PHPUnit on a single site and on multisite and the end-to-end tests, once for each combination of
  `npm run test:matrix`: `default`, `floor` and `latest`.

It uses the commands of this file, so a failure can be reproduced locally with the command named in the failed step.
The live test runs only when the repository has an `IFRAMELY_API_KEY` secret.

## Release

    npm run release

builds the scripts and styles and puts together what goes to wordpress.org, in the layout of its SVN repository:

| Folder           | Content                                                                               |
|------------------|---------------------------------------------------------------------------------------|
| `release/trunk`  | The plugin package: every file under version control that `.distignore` does not list |
| `release/assets` | Banners, icons and screenshots of the plugin page, from `svn/assets`                  |

To add a copy of the package for the version being released:

    npm run release -- --tag=1.2.0    # also fills release/tag/1.2.0

The tag has to be the `Version` of `iframely.php`. A release is made from committed files: the command stops when
files differ from what is committed, which after a build includes `build/`. Use `-- --allow-dirty` to package the
files as they are.

To leave a file out of the package, add it to `.distignore`.


## Update WordPress SVN

Copy `release/trunk`, `release/assets` and `release/tag/<version>` (as `tags/<version>`) into a checkout of the
plugin's SVN repository, then:

Rebase to latest revision

    svn up

Add new files to source control:

    svn add tags/* 
    svn add trunk/*

List the updates:

    svn stat

Commit to SVN:

    svn ci -m "version 1.1.1"