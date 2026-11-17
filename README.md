# Iframely WordPress plugin

## Requirements

- Node.js 22.13 or newer and npm
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
file. To try other versions, set `WP_ENV_CORE` and `WP_ENV_PHP_VERSION` and restart with `--update`, for example:

    WP_ENV_CORE=WordPress/WordPress#6.6 WP_ENV_PHP_VERSION=7.4 npx wp-env --config=.wp-env.tests.json start --update

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
    npm run test:js               # Jest unit tests (tests/js)
    npm run test:php              # PHPUnit integration tests (tests/php), single site
    npm run test:php:multisite    # the same tests on multisite
    npm run test:e2e              # Playwright end-to-end tests (tests/e2e) against the tests site

Arguments after `--` are passed through, e.g. `npm run test:php -- --filter SettingsTest`.

None of these tests talks to the real Iframely API:

- PHPUnit intercepts every HTTP request (`tests/php/TestCase.php`); a request without a declared response fails the test.
- The tests site loads `tests/e2e/mu-plugins/iframely-api-stub.php`, which answers API calls locally. It accepts any
  API key starting with `e2evalid`. The two scripts the editor loads from `if-cdn.com` are replaced in the browser.
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

### Update POT file
    gulp clean
    wp i18n make-pot . lang/iframely.pot  --headers="{\"Project-Id-Version\": \"Iframely\", \"Report-Msgid-Bugs-To\": \"https://wordpress.org/support/plugin/iframely/\", \"Last-Translator\": \"Iframely.com\", \"Language-Team\": \"Iframely.com\"}"
    wp i18n make-json lang --no-purge

## Release

Copy plugin files to `release` folder with proper SVN structure:

    gulp release

Alternatively, add specific version to `tags` directory:

    gulp release --tag=1.0

Clean `release` folder:

    gulp clean


## Update WordPress SVN

Rebase to latest revision

    svn up

Add new files to source control:

    svn add tags/* 
    svn add trunk/*

List the updates:

    svn stat

Commit to SVN:

    svn ci -m "version 1.1.1"