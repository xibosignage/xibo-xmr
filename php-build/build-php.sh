#!/bin/sh
#
# Copyright (C) 2026 Xibo Signage Ltd
#
# Xibo - Digital Signage - https://xibosignage.com
#
# This file is part of Xibo.
#
# Xibo is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# any later version.
#
# Xibo is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU Affero General Public License
# along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
#

#
# Build PHP 7.4 from source (remicollet/php-src-security, PHP-7.4-security-backports)
# so that we can ship security fixes which are no longer packaged by Alpine.
#
# XMR only needs the CLI, with the extension set previously provided by the Alpine 3.15
# php7, php7-json, php7-openssl, php7-phar and php7-zmq packages.
# Everything is installed under /usr/local, with configuration kept in /etc/php7.
#
# Expects the PHP source tree in /usr/src/php.
# The zmq PECL tarball and Alpine's zmq compile patches are downloaded at build time, each from several
# mirrors (pecl.php.net is not always available). Every download is verified against the sha512 pinned
# below: these are the exact sources Alpine 3.15 built its php7-pecl-zmq package from.
#
# Outputs:
#   /usr/local                     PHP binary, extensions and headers
#   /etc/php7                      php.ini and conf.d
#   /usr/local/php-run-deps        list of runtime shared library deps for apk
set -eux

apk add --no-cache \
    autoconf \
    bison \
    re2c \
    build-base \
    file \
    pkgconf \
    libxml2-dev \
    openssl-dev \
    pcre2-dev \
    zlib-dev \
    zeromq-dev \
    patch \
    curl

# Fetch a file into /usr/src/pecl, trying each mirror in turn until one serves the pinned content.
# Arguments: local file name, expected sha512, then candidate URLs.
pecl_fetch() {
    file="$1"; sha512="$2"; shift 2
    for url in "$@"; do
        if curl -fsSL --retry 3 --connect-timeout 20 --max-time 300 -o "/usr/src/pecl/$file" "$url"; then
            if echo "$sha512  /usr/src/pecl/$file" | sha512sum -c -; then
                return 0
            fi
            echo "Checksum mismatch: $url"
        else
            echo "Download failed: $url"
        fi
    done
    echo "Unable to download $file with the expected checksum from any mirror"
    return 1
}

mkdir -p /usr/src/pecl

pecl_fetch zmq-1.1.3.tgz \
    0f03016e315a6c6a1e1d6eca1e20564834d2a924cef06af1b38693933f1e5a094c37e9c432705a64f61d95e82655f61358fc7783e7a6f06c7dd9ad84fc6f7bbc \
    https://pecl.php.net/get/zmq-1.1.3.tgz \
    https://distfiles.alpinelinux.org/distfiles/v3.15/php7-pecl-zmq-1.1.3.tgz

# zmq 1.1.3 compile fixes, from the aports commit Alpine 3.15 built php7-pecl-zmq from
ZMQ_APORTS=5f9bc68c563fd8b2ba45d22fcad96b093158fa8e
ZMQ_APORTS_PATH=community/php7-pecl-zmq
pecl_fetch zmq-fix-php-7.3-compile.patch \
    3fda312b76106a1b78e35ffce3199bf52d040008e5fc9c134dd0b3f4a41ceef8857e8cae10b208dfd9b7394dfea652533a258f523da9b5f870d381bddd780797 \
    "https://gitlab.alpinelinux.org/alpine/aports/-/raw/$ZMQ_APORTS/$ZMQ_APORTS_PATH/fix-php-7.3-compile.patch" \
    "https://raw.githubusercontent.com/alpinelinux/aports/$ZMQ_APORTS/$ZMQ_APORTS_PATH/fix-php-7.3-compile.patch" \
    "https://git.alpinelinux.org/aports/plain/$ZMQ_APORTS_PATH/fix-php-7.3-compile.patch?id=$ZMQ_APORTS"
pecl_fetch zmq-fix-php-7.4-compile.patch \
    58288af1fd1441491c7f5cc7be967ba8e806a8d80141d4c3699af9a977ede57f3f464922bc218f3aabdce965699fea5a016954ca538f98559553625d33ff2d4f \
    "https://gitlab.alpinelinux.org/alpine/aports/-/raw/$ZMQ_APORTS/$ZMQ_APORTS_PATH/fix-php-7.4-compile.patch" \
    "https://raw.githubusercontent.com/alpinelinux/aports/$ZMQ_APORTS/$ZMQ_APORTS_PATH/fix-php-7.4-compile.patch" \
    "https://git.alpinelinux.org/aports/plain/$ZMQ_APORTS_PATH/fix-php-7.4-compile.patch?id=$ZMQ_APORTS"

export CFLAGS="-fstack-protector-strong -fpic -fpie -O2 -D_LARGEFILE_SOURCE -D_FILE_OFFSET_BITS=64"
export CPPFLAGS="$CFLAGS"
export LDFLAGS="-Wl,-O1 -pie"

cd /usr/src/php

# A git checkout has no generated configure script or parser/scanner sources
./buildconf --force

# Only the extensions XMR loaded from Alpine packages are enabled (--disable-all), built in
# statically so that no per-extension ini files are needed.
./configure \
    --prefix=/usr/local \
    --with-config-file-path=/etc/php7 \
    --with-config-file-scan-dir=/etc/php7/conf.d \
    --disable-all \
    --disable-cgi \
    --disable-phpdbg \
    --with-pic \
    --enable-filter \
    --enable-json \
    --with-libxml \
    --with-openssl \
        --with-system-ciphers \
    --with-external-pcre \
    --enable-phar \
    --with-zlib

make -j"$(nproc)"
make install
make clean

mkdir -p /etc/php7/conf.d
cp php.ini-production /etc/php7/php.ini

# zmq 1.1.3 needs Alpine's patches to compile against 7.3/7.4
mkdir -p /usr/src/pecl-build
cd /usr/src/pecl-build
tar -xzf /usr/src/pecl/zmq-1.1.3.tgz --strip-components=1
patch -p1 < /usr/src/pecl/zmq-fix-php-7.3-compile.patch
patch -p1 < /usr/src/pecl/zmq-fix-php-7.4-compile.patch
phpize
./configure
make -j"$(nproc)"
make install
cd /usr/src
rm -rf /usr/src/pecl-build
echo "extension=zmq.so" > /etc/php7/conf.d/50_zmq.ini

# Strip binaries
strip --strip-all /usr/local/bin/php /usr/local/lib/php/extensions/*/*.so

# Record the shared libraries PHP links against, so the runtime image can apk add exactly those
scanelf --needed --nobanner --format '%n#p' --recursive /usr/local \
    | tr ',' '\n' \
    | sort -u \
    | awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }' \
    > /usr/local/php-run-deps
cat /usr/local/php-run-deps

# Record what we built
php -v
php -m
