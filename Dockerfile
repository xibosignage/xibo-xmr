FROM composer:1.6 as composer
COPY . /app
RUN composer install --no-interaction --no-dev --ignore-platform-reqs --optimize-autoloader

# Build PHP 7.4 from the security backports branch (Alpine no longer packages fixes for 7.4)
# PHP_SRC_REF is pinned to a reviewed commit; bump it when new backports land.
# 802c2107: GHSA-w8hw-w9fx-j79w (CVE-2026-109618), GHSA-6wmf-4rv5-p3rp (CVE-2026-109619)
FROM alpine:3.15 as php
ARG PHP_SRC_REF=802c21075308066362aab4993478dcd0577993a6
ADD https://github.com/remicollet/php-src-security/archive/${PHP_SRC_REF}.tar.gz /usr/src/php.tar.gz
COPY php-build/build-php.sh /usr/src/build-php.sh
RUN mkdir /usr/src/php && \
    tar -xzf /usr/src/php.tar.gz -C /usr/src/php --strip-components=1 && \
    rm /usr/src/php.tar.gz && \
    sh /usr/src/build-php.sh

FROM alpine:3.15
MAINTAINER Xibo Signage Ltd <info@xibo.org.uk>

ENV XMR_DEBUG false
ENV XMR_QUEUE_POLL 5
ENV XMR_QUEUE_SIZE 10
ENV XMR_IPV6RESPSUPPORT false
ENV XMR_IPV6PUBSUPPORT false

# PHP built from source in the php stage
COPY --from=php /usr/local/bin/php /usr/local/bin/php
COPY --from=php /usr/local/lib/php /usr/local/lib/php
COPY --from=php /usr/local/php-run-deps /usr/local/php-run-deps
COPY --from=php /etc/php7 /etc/php7

RUN apk update && apk upgrade && apk add tar \
    curl \
    $(cat /usr/local/php-run-deps) \
    && rm -rf /var/cache/apk/* \
    && ln -s /usr/local/bin/php /usr/bin/php \
    && ln -s /usr/local/bin/php /usr/bin/php7

EXPOSE 9505 50001

COPY ./entrypoint.sh /entrypoint.sh
COPY . /opt/xmr
COPY --from=composer /app/vendor /opt/xmr/vendor

RUN rm -r /opt/xmr/php-build && chown -R nobody /opt/xmr && chmod 755 /entrypoint.sh

# Start XMR
USER nobody

CMD ["/entrypoint.sh"]