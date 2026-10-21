# Test image: the XMR image plus dev dependencies, so tests run on the same PHP/extensions we ship.
# Built by tests/run.sh
ARG XMR_IMAGE=xmr-test
FROM composer AS composer

FROM ${XMR_IMAGE}
COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN apt-get update && apt-get install -y unzip && rm -rf /var/lib/apt/lists/*

WORKDIR /opt/xmr
RUN composer install --no-interaction --ignore-platform-reqs

CMD ["vendor/bin/phpunit"]
