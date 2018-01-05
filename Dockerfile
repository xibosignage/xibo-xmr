FROM php:7.0-cli
MAINTAINER Spring Signage Ltd <info@xibo.org.uk>

ENV XMR_DEV_MODE false
ENV XMR_DEBUG false
ENV XMR_QUEUE_POLL 5
ENV XMR_QUEUE_SIZE 10
ENV XMR_IPV6RESPSUPPORT false
ENV XMR_IPV6PUBSUPPORT false

RUN apt-get -y update && \
    apt-get -y install libzmq-dev

# ZMQ
RUN pecl install zmq-beta && \
    docker-php-ext-enable zmq

EXPOSE 9505 50001

# Copy up the various provisioning scripting
RUN mkdir -p /opt/xmr

# Copy XMR into a convenient folder
COPY . /opt/xmr

RUN chown -R nobody /opt/xmr && chmod 700 /opt/xmr/entrypoint.sh

# Start XMR
CMD ["/opt/xmr/entrypoint.sh"]