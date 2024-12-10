#!/bin/sh

# Write config.json
echo '{' > /opt/xmr/config.json
echo '  "sockets": {' >> /opt/xmr/config.json
echo '    "ws": "0.0.0.0:8080",' >> /opt/xmr/config.json
echo '    "api": "0.0.0.0:8081",' >> /opt/xmr/config.json
echo '    "zmq": ["tcp://*:9505"]' >> /opt/xmr/config.json
echo '  },' >> /opt/xmr/config.json
echo '  "queuePoll": '$XMR_QUEUE_POLL',' >> /opt/xmr/config.json
echo '  "queueSize": '$XMR_QUEUE_SIZE',' >> /opt/xmr/config.json
echo '  "debug": '$XMR_DEBUG',' >> /opt/xmr/config.json
echo '  "ipv6PubSupport": '$XMR_IPV6PUBSUPPORT >> /opt/xmr/config.json
echo '}' >> /opt/xmr/config.json

/usr/local/bin/php /opt/xmr/index.php