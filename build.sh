#!/usr/bin/env bash
docker run --rm --name xmr-build \
    -v "$PWD":/usr/src/myapp \
    -w /usr/src/myapp composer \
    /bin/bash -c "echo 'phar.readonly = Off' > /usr/local/etc/php/php.ini; curl -LSs https://github.com/box-project/box/releases/download/4.2.0/box.phar -o box.phar; php box.phar compile; rm box.phar"
