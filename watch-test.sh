#!/bin/sh

phpunit=phpunit
if [ -f ./vendor/bin/phpunit ]; then
    phpunit='php ./vendor/bin/phpunit';
fi;

while i=$(inotifywait -qre modify --exclude ".git|node_modules|vendor" ./); do
    set -- $i;
    dir=$1
    evt=$2
    file=$3
    ext=${file##*.}
    echo "$evt '$file'";

    case $dir in
        *tests/) isTestDir="$dir" ;;
        *) isTestDir= ;;
    esac

    testPath=./tests/
    case $ext in
        html|json)
            eval "$phpunit $testPath"
        ;;
        php)
            if [ -n "$isTestDir" ]; then
                testPath="$testPath$file"
            fi
            eval "$phpunit $testPath"
        ;;
    esac
    # make run_test
done
