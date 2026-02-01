#!/bin/sh

cd "$(dirname "$0")" && (
    if [ ! -s 'vendor/autoload.php' ]; then
        if command -v composer > /dev/null; then
            echo "composer install..."
            composer install --optimize-autoloader
        else
            >&2 echo "Please, install composer and run \`compose install\` in $PWD!"
        fi
    fi

    php -S localhost:8000
)
