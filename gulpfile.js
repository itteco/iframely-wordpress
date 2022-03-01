let gulp = require('gulp');
let fs = require('fs');
let mri  = require('mri');

let config = {
  dest: {
    root: 'release',
    assets: 'release/assets',
    plugin: 'release/trunk',
    tag: 'release/tag'
  },
  src: {
    release: 'release/trunk/**/*.*',
    assets: 'svn/assets/*.*',
    plugin: [
      'app*/**/*',
      'assets*/**/*',
      'build*/**/*',
      'lang*/**/*.pot',
      'views*/**/*',
      'iframely.php',
      'index.php',
      'readme.txt',
    ],
  },
};

function clean(ob) {
  fs.rmSync(config.dest.root, { recursive: true, force: true });
  ob();
}

function copyAssets() {
  return gulp.src(config.src.assets).pipe(gulp.dest(config.dest.assets));
}

function copyPlugin() {
  return gulp.src(config.src.plugin).pipe(gulp.dest(config.dest.plugin));
}

function copyTag(ob) {
  let argv = mri(process.argv.slice(2), {
    string: 'tag',
  });
  let tag = argv.tag || false;
  if (tag) {
    let dest = `${config.dest.tag}/${tag}`;
    return gulp.src(config.src.release).pipe(gulp.dest(dest));
  }
  ob();
}

gulp.task('clean', gulp.series(clean));

gulp.task('release', gulp.series(clean, copyAssets, copyPlugin, copyTag));

gulp.task('default', gulp.series('release'));
