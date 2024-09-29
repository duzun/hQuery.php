/*jshint
    esversion: 6,
    node: true
*/

const gulp = require('gulp'),
      php = require('gulp-connect-php'),
      browserSync = require('browser-sync');
const path = require('path');
const fs = require('fs');
const { spawn } = require('node:child_process');

var reload = browserSync.reload;

// - gulp live - start a PHP web-server and browsersync for live-reload

gulp.task('php', function () {
    const base = './examples';
    php.server({ base, port: 8010, keepalive: true });

    if (!fs.existsSync(path.join(base, 'vendor/autoload.php'))) {
        setTimeout(() => {
            console.log(`composer install...\n`);
            const composerInstall = spawn('composer', ['install', '--optimize-autoloader'], { cwd: base });
            const stderr = [];
            composerInstall.stderr.on('data', (data) => {
                stderr.push(data);
            });
            composerInstall.on('close', (code) => {
                console.log(`composer install done, code=${code}`);
                if (code && stderr.length) {
                    console.error(`composer install errors:\n${stderr.join('')}`);
                }
            });
        }, 1e3);
    }
});

gulp.task('browser-sync', gulp.parallel('php', function () {
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
