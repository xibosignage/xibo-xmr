FROM composer AS composer
COPY . /app
RUN composer install --no-interaction --no-dev --ignore-platform-reqs --optimize-autoloader

# Build the PHAR file
RUN echo 'phar.readonly = Off' > /usr/local/etc/php/php.ini;
RUN curl -LSs https://github.com/box-project/box/releases/download/4.6.10/box.phar -o box.phar; php box.phar compile; rm box.phar

FROM php:8.5-cli
LABEL org.opencontainers.image.authors="Xibo Signage Ltd <info@xibosignage.com>"

ENV XMR_DEBUG=false \
    XMR_QUEUE_POLL=5 \
    XMR_QUEUE_SIZE=10 \
    XMR_IPV6PUBSUPPORT=false \
    XMR_RELAY_OLD_MESSAGES=false \
    XMR_RELAY_MESSAGES=false \
    XMR_SOCKETS_WS=0.0.0.0:8080 \
    XMR_SOCKETS_API=0.0.0.0:8081 \
    XMR_SOCKETS_ZM_PORT=9505 \
    XMR_PHP_MEMORY_LIMIT=256M

# Install dependencies
RUN apt-get update && apt-get install -y \
    libzmq3-dev \
    libev-dev \
    git \
    util-linux \
    && rm -rf /var/lib/apt/lists/*

RUN pecl install ev && docker-php-ext-enable ev

# php-zmq is unmaintained: zend_exception_get_default() was removed in PHP 8.5, use zend_ce_exception instead
RUN git clone https://github.com/zeromq/php-zmq.git \
    && cd php-zmq \
    && sed -i 's/zend_exception_get_default()/zend_ce_exception/' zmq.c \
    && phpize && ./configure \
    && make \
    && make install \
    && cd .. \
    && rm -fr php-zmq

RUN docker-php-ext-enable zmq

# Opcache is built in from PHP 8.5, ensure its running for CLI
RUN echo "opcache.enable_cli=1" >> /usr/local/etc/php/conf.d/opcache.ini
RUN echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache.ini

EXPOSE 8080 8081 9505

COPY ./entrypoint.sh /entrypoint.sh
COPY . /opt/xmr
COPY --from=composer /app/vendor /opt/xmr/vendor
COPY --from=composer /app/bin /opt/xmr/bin

RUN chown -R nobody /opt/xmr && chmod 755 /entrypoint.sh && chmod +x /entrypoint.sh

# Start as root (entrypoint drops to nobody)
CMD ["/entrypoint.sh"]