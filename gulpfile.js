/*jshint
    esversion: 6,
    node: true
*/

const gulp = require('gulp'),
      php = require('gulp-connect-php'),
      browserSync = require('browser-sync');

var reload  = browserSync.reload;

// - gulp live - start a PHP web-server and browsersync for live-reload

gulp.task('php', function() {
    php.server({ base: './examples', port: 8010, keepalive: true});
});

gulp.task('browser-sync', gulp.parallel('php', function() {
    browserSync({
        proxy: '127.0.0.1:8010',
        port: 8080,
        open: true,
        notify: false
    });
}));

gulp.task('default', gulp.parallel('browser-sync', function () {
    gulp.watch(['*.php', 'examples/*.php'])
        .on('change', reload)
        .on('add', reload)
        .on('unlink', function (path, stats) {
            reload();
            watcher.unwatch(path);
        })
    ;
}));
