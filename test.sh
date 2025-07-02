#!/bin/sh

workdir=$(pwd)
myname=$(basename "$0")

PHPUNIT_VERSION_84=^12.0
PHPUNIT_VERSION_83=^12.0
PHPUNIT_VERSION_82=^11.0
PHPUNIT_VERSION_81=^10.2
PHPUNIT_VERSION_74=^9.6
PHPUNIT_VERSION_73=^9.6
PHPUNIT_VERSION_72=^8.5
PHPUNIT_VERSION_71=^7.5
PHPUNIT_VERSION_70=^6.5 #^5.7
PHPUNIT_VERSION_56=^5.7
PHPUNIT_VERSION_55=^4.8
PHPUNIT_VERSION_54=^4.8
PHPUNIT_VERSION_53=^4.8

DOCKER_VERSION_54=cli
DOCKER_VERSION_53=cli

# flags to pass to install
flags="--prefer-dist --no-interaction --optimize-autoloader --no-suggest --no-progress"

usage() {
    cat <<EOH
    $myname <php.ver>|"all" [w] {<phpunit_options>}
    $myname <php.ver> [sh|bash|ex]
    $myname -h|--help|?
Eg.
    # Run unit-tests in PHP 8.2 with watch
    $myname 8.2 w --filter hQueryCore

    # Launch web-server in examples/ using PHP 7.4
    $myname 7.4 ex

EOH
}

var() {
    eval "echo \${$1:-$2}"
}

ver_num() {
    echo "$1" | cut -d- -f1 | sed 's/\.//g'
}

main_in_docker() {
    install_dev "$1" || return $?
    shift

    local watch
    if [ "$1" = "w" ]; then
        watch=1
        shift
        if ! command -v inotifywait >/dev/null; then
            apk -U add inotify-tools
        fi
    fi

    echo
    echo
    echo " - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -"
    echo
    local c
    [ -s "$workdir/tests/phpunit.xml" ] && c="-c $workdir/tests/phpunit.xml"
    phpunit tests/ $c "$@"

    if [ -n "$watch" ]; then
        watchnrun "$workdir" \
            phpunit tests/ $c "$@"
    fi

    cd "$workdir" && [ -s 'composer.json.lock' ] && mv -f -- composer.json.lock composer.json
}

# Watch a folder and rsync files to a destination on change
watchnrun() {
    local watchDir="$1"
    shift
    local action="$@"

    if [ -z "$watchDir" ]; then
        echo >&2 "Usage: watchnrun <watchDir> <command>"
        return 2
    fi

    local exclude=".git|node_modules|vendor|composer.json|composer.lock|composer-setup.php"

    while i=$(
        inotifywait -qr -e modify -e create \
            --exclude "$exclude" \
            "$watchDir"
    ); do
        set -- $i

        local dir=$1
        local evt=$2
        local file=$3
        local _action

        local fn="$dir$file"

        echo
        echo "$evt $fn"

        case $evt in
        MODIFY | CREATE)
            case $file in
            *.Test.php)
                set -- $action
                _action=
                while [ $# -ne 0 ]; do
                    if [ "$1" = "--filter" ]; then
                        shift
                    else
                        _action="$_action $1"
                    fi
                    shift
                done
                i=${file%.*}
                _action="$_action --filter ${i%.*}"
                ;;
            *)
                _action="$action"
                ;;
            esac

            eval "$_action"
            ;;
        *)
            echo >&2 "evt $evt ($i) not implemented yet"
            ;;
        esac
    done
}

install_dev() {
    local version version_num phpunit_ver preinstall composer

    version=${1:?}
    version_num=$(ver_num "$version")
    phpunit_ver=$(var "PHPUNIT_VERSION_$version_num")
    shift

    echo "PHP ver: $version"
    echo "PHPUnit ver: $phpunit_ver"

    if [ -z "$phpunit_ver" ]; then
        echo >&2 "No PHPUnit version associated with this version of PHP"
        return 2
    fi

    # php < 5.6
    if php -r "die(+version_compare(PHP_VERSION,'5.6','>='));"; then
        curl -ks -L https://curl.se/ca/cacert.pem >/etc/ssl/certs/ca-certificates.crt
    fi

    preinstall="preinstall_dev_$version_num"

    type "$preinstall" >/dev/null &&
        $preinstall

    # if ! command -v git >/dev/null || ! command -v unzip >/dev/null; then
    #     apk -U add git unzip
    #     # apt update && apt install -y git unzip
    # fi

    composer="$workdir/vendor/bin/composer$version_num"

    if [ ! -s "$composer" ]; then
        # Install composer
        [ -d "$(dirname "$composer")" ] || mkdir -p "$(dirname "$composer")"
        php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
        php composer-setup.php
        # php composer-setup.php --disable-tls
        mv -- composer.phar "$composer"
        php -r "unlink('composer-setup.php');"
    fi
    export PATH="$(realpath "$workdir/vendor/bin"):$PATH"

    # Running the tests for a specific PHP version should not change composer.json
    [ -s composer.json.lock ] ||
        cp -f -- composer.json composer.json.lock

    trap "cd '$workdir' && [ -s composer.json.lock ] && mv -f -- composer.json.lock composer.json" \
        INT TERM EXIT

    # php-http/discovery installs a composer plugin, that is breaking backwards compatibility,
    # thus remove it before running any composer command.
    rm -rf -- vendor/php-http/discovery/

    "$composer" require --dev "phpunit/phpunit:$phpunit_ver" -W
    # "$composer" dump-autoload

    # install composer dependencies
    if php -r "die(+(version_compare(PHP_VERSION,'5.5')!=1));"; then
        "$composer" install $flags
    else
        "$composer" dump-autoload
    fi

    # Update some dependencies to this PHP version
    "$composer" require --dev symfony/css-selector symfony/dom-crawler

    # Some dependencies are available for PHP >= 5.5 only
    if php -r "die(+version_compare(PHP_VERSION,'5.5','<'));"; then
        "$composer" require --dev php-http/mock-client php-http/discovery guzzlehttp/psr7 php-http/message php-http/message-factory
    fi

    # Remove some tools not required for testing
    "$composer" remove --dev apigen/apigen
}

# preinstall_dev_54() {
#     if ! command -v unzip >/dev/null; then
#         apt update && apt install -y unzip
#     fi
# }

# preinstall_dev_53() {
#     if ! command -v unzip >/dev/null; then
#         apt update && apt install -y unzip
#     fi
# }

# preinstall_dev_55() {
#     curl -ks https://curl.se/ca/cacert.pem >/etc/ssl/certs/ca-certificates.crt
# }

docker_run() {
    docker run --rm -v "$workdir:/app" -w /app "$@"
}

main() {
    # By default test the latest PHP version
    [ $# -eq 0 ] && set -- "8.2"

    case $1 in
    main_in_docker)
        shift
        main_in_docker "$@"
        return $?
        ;;

    all)
        shift
        echo Running tests for all supported PHP versions &&
            main 8.4 "$@" &&
            main 8.3 "$@" &&
            main 8.2 "$@" &&
            main 8.1 "$@" &&
            main 7.4 "$@" &&
            main 7.3 "$@" &&
            main 7.2 "$@" &&
            main 7.1 "$@" &&
            main 7.0 "$@" &&
            main 5.6 "$@" &&
            main 5.5 "$@" &&
            echo && echo "All done"
        ;;

    h | help | -h | --help | \?)
        usage
        return 0
        ;;

    esac

    local version docker_tag

    version=$1
    docker_tag="$version-$(var "DOCKER_VERSION_$(ver_num "$version")" 'alpine')"

    case $2 in
    bash | sh)
        shift
        docker_run -it "php:$docker_tag" "$@"
        return $?
        ;;
    ex | examples)
        shift
        local port container browser

        port="80$(ver_num "$version")"
        container="hquery-php-$docker_tag"

        echo "Running a php:$docker_tag container $container ..."
        docker_run -it -p "127.0.0.1:$port:$port" --name "$container" -d "php:$docker_tag" \
            sh -c "cd /app/examples && exec php -S 0.0.0.0:$port" || return $?
        trap "echo 'Stopping $container ...' && docker stop '$container'" INT TERM EXIT

        for i in xdg-open open explorer firefox google-chrome chrome; do
            if command -v "$i" >/dev/null; then
                browser="$i"
                break
            fi
        done

        if [ -n "$browser" ]; then
            echo "Opening http://localhost:$port ..."
            "$browser" "http://localhost:$port"
            sleep 2
        else
            echo "Open http://localhost:$port in your favorite browser."
        fi

        echo
        echo "Press Ctrl+C when done"
        echo
        docker logs -f "$container"
        return $?
        ;;
    esac

    rm -f -- "$workdir/composer.lock"

    if [ -f /.dockerenv ]; then
        main_in_docker "$@"
    else
        docker_run -it "php:$docker_tag" sh "/app/$myname" "main_in_docker" "$@"
    fi
}

main "$@"
