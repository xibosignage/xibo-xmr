FROM composer AS composer
COPY . /app
RUN composer install --no-interaction --no-dev --ignore-platform-reqs --optimize-autoloader

FROM php:8.2-cli
LABEL org.opencontainers.image.authors="Xibo Signage Ltd <info@xibosignage.com>"

ENV XMR_DEBUG=false
ENV XMR_QUEUE_POLL=5
ENV XMR_QUEUE_SIZE=10
ENV XMR_IPV6PUBSUPPORT=false

RUN apt-get update && apt-get install -y libzmq3-dev git \
    && rm -rf /var/lib/apt/lists/*

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

RUN chown -R nobody /opt/xmr && chmod 755 /entrypoint.sh

# Start XMR
USER nobody

CMD ["/entrypoint.sh"]