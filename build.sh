#!/usr/bin/env bash
docker run --rm --name xmr-build \
    -v "$PWD":/usr/src/myapp \
    -w /usr/src/myapp php:7.0-cli \
    /bin/bash -c "echo 'phar.readonly = Off' > /usr/local/etc/php/php.ini; curl -LSs https://box-project.github.io/box2/installer.php | php; php box.phar build; rm box.phar"

#docker rmi composer
#docker rmi php:7.0-cli