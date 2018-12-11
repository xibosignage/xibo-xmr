# Stage: Composer
FROM composer:1.6 as composer
COPY . /app
RUN composer install --no-interaction --no-dev --ignore-platform-reqs --optimize-autoloader


# Stage: System
FROM alpine:3.6
MAINTAINER Spring Signage Ltd <info@xibo.org.uk>

ENV XMR_DEBUG=false \
    XMR_QUEUE_POLL=5 \
    XMR_QUEUE_SIZE=10 \
    XMR_IPV6RESPSUPPORT=false \
    XMR_IPV6PUBSUPPORT=false

RUN apk update && \
    apk upgrade && \
    apk add tar php7 curl php7-zmq php7-phar php7-json php7-openssl

EXPOSE 9505 50001

COPY ./docker/entrypoint.sh /entrypoint.sh
COPY . /opt/xmr
COPY --from=composer /app/vendor /opt/xmr/vendor

# Configuration
COPY ./docker/config.json /opt/xmr/

RUN chown -R nobody /opt/xmr && \
    chmod 0666 /opt/xmr/config.json && \
    chmod 0755 /entrypoint.sh

USER nobody

CMD ["/entrypoint.sh"]
