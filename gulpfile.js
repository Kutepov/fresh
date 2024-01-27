let gulp = require('gulp');
let git = require('gulp-git');
let concat = require('gulp-concat');
let cssnano = require('gulp-cssnano');
let uglify = require('gulp-uglify');
let replace = require('gulp-replace');

gulp.task('css', async function () {
    return gulp
        .src([
            './styles_sources/buzz/site/build/css/style.css',
        ])
        .pipe(concat('app.min.css', {
            newLine: '\n;'
        }))
        .pipe(cssnano({
            zindex: false,
            discardComments: {
                removeAll: true
            }
        }))
        .pipe(replace('};', '}'))
        .pipe(gulp.dest('./buzz/web/css'));
});

gulp.task('common-js', async function () {
    return gulp
        .src([
            './buzz/assets/src/js/clipboard.min.js',
            // './buzz/assets/src/js/jquery.min.js',
            './buzz/assets/src/js/app.js',
            './buzz/assets/src/js/comments.js',
            './buzz/assets/src/js/youtube.js',
            './buzz/assets/src/js/twitter.js',
            './styles_sources/buzz/site/build/js/scripts.js',
        ])
        .pipe(concat('app.min.js', {
            newLine: '\n;'
        }))
        .pipe(uglify({
            output: {
                quote_keys: true
            }
        }))
        .pipe(replace('o.delete(t)', 'o["delete"](t)'))
        .pipe(gulp.dest('./buzz/web/js'));
});


gulp.task('static', async function () {
    return gulp
        .src([
            './styles_sources/buzz/site/build/favicon/**/*',
            './styles_sources/buzz/site/build/img/**/*',
            './styles_sources/buzz/site/build/fonts/**/*'
        ], {
            base: './styles_sources/buzz/site/build'
        })
        .pipe(gulp.dest('./buzz/web'));
});

gulp.task('git', function () {
    return gulp
        .src([
            './buzz/web/favicon/*',
            './buzz/web/js/*',
            './buzz/web/css/*',
            './buzz/web/img/*',
        ])
        .pipe(git.add());
});

gulp.task('default', gulp.series(
    gulp.parallel(
        'css',
        'common-js',
        'static',
    ),
    'git'
));
