# Iframely WordPress plugin

## Installation
    npm install

### Gutenberg build
    npm run build

### Gutenberg watch
    npm run start

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