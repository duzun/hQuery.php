#!/bin/sh

workdir=$(pwd)
myname=$(basename "$0")

PHPUNIT_VERSION_82=^10.2
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

var() {
    eval "echo \${$1:-$2}"
}

ver_num() {
    echo "$1" | cut -d- -f1 | sed 's/\.//g'
}

main_in_docker() {
    install_dev "$1" || return $?
    shift

    echo
    echo
    echo " - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -"
    echo
    phpunit tests/ "$@"
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
    case $1 in
    main_in_docker)
        shift
        main_in_docker "$@"
        return $?
        ;;

    all)
        echo Running tests for all supported PHP versions \
            && main 8.2 \
            && main 8.1 \
            && main 7.4 \
            && main 7.3 \
            && main 7.2 \
            && main 7.1 \
            && main 7.0 \
            && main 5.6 \
            && main 5.5 \
            && echo && echo "All done"
        ;;
    esac

    local version docker_tag

    version=${1:-"8.1"}
    docker_tag="$version-$(var "DOCKER_VERSION_$(ver_num $version)" 'alpine')"

    case $2 in
    bash | sh)
        shift
        docker_run -it "php:$docker_tag" "$@"
        return $?
        ;;
    esac

    rm -f -- "$workdir/composer.lock"

    if [ -f /.dockerenv ]; then
        main_in_docker "$@"
    else
        docker_run "php:$docker_tag" sh "/app/$myname" "main_in_docker" "$@"
    fi
}

main "$@"
