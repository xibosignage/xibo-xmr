FROM composer AS composer
COPY . /app
RUN composer install --no-interaction --no-dev --ignore-platform-reqs --optimize-autoloader

# Build the PHAR file
RUN echo 'phar.readonly = Off' > /usr/local/etc/php/php.ini;
RUN curl -LSs https://github.com/box-project/box/releases/download/4.2.0/box.phar -o box.phar; php box.phar compile; rm box.phar

FROM php:8.2-cli
LABEL org.opencontainers.image.authors="Xibo Signage Ltd <info@xibosignage.com>"

ENV XMR_DEBUG=false
ENV XMR_QUEUE_POLL=5
ENV XMR_QUEUE_SIZE=10
ENV XMR_IPV6PUBSUPPORT=false
ENV XMR_RELAY_OLD_MESSAGES=false
ENV XMR_RELAY_MESSAGES=false
ENV XMR_SOCKETS_WS=0.0.0.0:8080
ENV XMR_SOCKETS_API=0.0.0.0:8081
ENV XMR_SOCKETS_ZM_PORT=9505

RUN apt-get update && apt-get install -y libzmq3-dev libev-dev git \
    && rm -rf /var/lib/apt/lists/*

RUN pecl install ev && docker-php-ext-enable ev

RUN git clone https://github.com/zeromq/php-zmq.git \
    && cd php-zmq \
    && phpize && ./configure \
    && make \
    && make install \
    && cd .. \
    && rm -fr php-zmq

RUN docker-php-ext-enable zmq

EXPOSE 8080 8081 9505

COPY ./entrypoint.sh /entrypoint.sh
COPY . /opt/xmr
COPY --from=composer /app/vendor /opt/xmr/vendor
COPY --from=composer /app/bin /opt/xmr/bin

RUN chown -R nobody /opt/xmr && chmod 755 /entrypoint.sh

# Start XMR
USER nobody

CMD ["/entrypoint.sh"]