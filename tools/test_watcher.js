/*jshint
    node: true,
    esversion: 6
*/

var spawn = require('child_process').spawn;
var watch = require('watch');
var path  = require('path');
var fs    = require('fs');
var http  = require('https');

var _to      = null;
var _delay   = 300;
var _running = null;
var _dir     = path.join(__dirname, '..');
var _test_dir = path.join(_dir, 'tests');
var _phpunit_url = 'https://phar.phpunit.de/phpunit.phar';
var _phpunit_path = path.join(__dirname, '..', 'vendor/bin/phpunit');
var _test_file = _test_dir;
var _test_args    = [];


function run_test() {
    _to = null;

    if ( _running ) {
      console.log('In the process...');
      return;
    }

    console.log('\n\x1b[36m --- Running PHPUnit ... ---\x1b[0m\n');

    var args = [_phpunit_path];

    var phpunit_xml = path.join(_test_dir, 'phpunit.xml');

    if ( fs.existsSync(phpunit_xml) ) {
        args.push('-c');
        args.push(phpunit_xml);
    }
    if ( _test_args ) args = args.concat(_test_args);
    args.push(_test_file);

    _running = spawn( 'php', args, { cwd: _dir, env: process.env } );

    _running.stdout.pipe(process.stdout);
    _running.stderr.pipe(process.stderr);

    // Coult use on('data') instead of pipe(),
    // but pipe() streams data as soon as available:
    // var out = ''
    // ,   err = ''
    // ;
    // _running.stdout.on('data', function (data) { out += data; });
    // _running.stderr.on('data', function (data) { err += data; });

    _running.on('close', function (code) {
      // out && console.log(out);
      // err && console.log(err);

      // Have errors
      if ( code ) {
        console.log('\x1b[31mFAIL (%d)\x1b[0m', code);
      }
      // Ok
      else {
        console.log('\x1b[32mPASS All\x1b[0m');
      }
      _running = null;
    });
}

function get_test_file_path(fn) {
    if ( fn ) {
        if ( path.extname(fn) != '.php' ) {
            fn += '.Test.php';
        }
        var _fn = path.join(_test_dir, fn);
        if ( fs.existsSync(_fn) ) {
            return _test_file = _fn;
        }
        else {
            var files = fs.readdirSync(_test_dir);
            var found = false;
            if ( files ) {
                files.forEach(function (_fn) {
                    if ( path.extname(_fn) ) return; // not a dir, possibly :-)
                    _fn = path.join(_test_dir, _fn, fn);
                    if ( fs.existsSync(_fn) ) {
                        found = true;
                        _test_file = _fn;
                        return false;
                    }
                });
            }
            if ( found ) return _test_file;
        }
        return false;
    }
    else {
        // Run all tests
        return _test_file = _test_dir;
    }
}


function run_test_async() {
    if ( _to ) {
      clearTimeout(_to);
    }
    _to = setTimeout(run_test, _delay);
}

function check_phpunit_phar(cb) {
    if ( !fs.existsSync(_phpunit_path) ) {
        let which, _path;
        try {
            which = require('which');
        } catch(e) {}
        if ( which ) {
            _path = which.sync('phpunit', {nothrow: true});
            if ( _path ) _phpunit_path = _path;
        }
        if ( _path ) cb();
        else download(_phpunit_url, _phpunit_path, 7, cb);
    }
    else {
        cb();
    }
}

function download(srcUrl, filePath, maxRedirects, cb) {
    return http.get(srcUrl, function onGet(response) {
        var statusCode = response.statusCode;
        var newUrl = response.headers.location;
        console.log('↓', srcUrl, '→', statusCode);
        if ( statusCode >= 300 && statusCode < 400 && newUrl ) {
            if ( !maxRedirects-- ) {
                return cb(new Error('Redirects detected (to "'+newUrl+'"")'));
            }
            return download(newUrl, filePath, maxRedirects, cb);
        }
        var file = fs.createWriteStream(filePath);
        response.pipe(file);
        response.on('end', cb);
    });
}

check_phpunit_phar(function () {
    var testcase = process.argv[2];
    _test_file = get_test_file_path(testcase);
    if ( false === _test_file ) {
        console.error("Can't find TestCase `" + testcase + "`");
        process.exit(1);
    }
    _test_args = process.argv.slice(3);
    if ( _test_dir != _test_file ) {
        console.log("Using '%s'", _test_file.slice(_test_dir.length+1));
    }

    run_test_async();

    watch.createMonitor(
      _dir,
      {
        interval: (_delay >>> 1) / 1e3,
        ignoreDotFiles: true,
        ignoreDirectoryPattern: /(node_modules|scripts|tools)/,
        filter: function (f, stat) { return stat.isDirectory() || path.extname(f) === '.php'; }
      },
      function (monitor) {
        // monitor.files['/home/mikeal/.zshrc'] // Stat object for my zshrc.
        monitor.on("created", function (f, stat) {
            run_test_async();
        });
        monitor.on("changed", function (f, curr, prev) {
          run_test_async();
        });
        monitor.on("removed", function (f, stat) {
          run_test_async();
        });
        // monitor.stop(); // Stop watching
      }
    );
});
