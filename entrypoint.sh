#!/bin/sh

# Write config.json
echo '{' > /opt/xmr/config.json
echo '  "listenOn": "tcp://*:50001",' >> /opt/xmr/config.json
echo '  "pubOn": ["tcp://*:9505"],' >> /opt/xmr/config.json
echo '  "queuePoll": '$XMR_QUEUE_POLL',' >> /opt/xmr/config.json
echo '  "queueSize": '$XMR_QUEUE_SIZE',' >> /opt/xmr/config.json
echo '  "debug": '$XMR_DEBUG',' >> /opt/xmr/config.json
echo '  "ipv6RespSupport": '$XMR_IPV6RESPSUPPORT',' >> /opt/xmr/config.json
echo '  "ipv6PubSupport": '$XMR_IPV6PUBSUPPORT >> /opt/xmr/config.json
echo '}' >> /opt/xmr/config.json

/usr/bin/php7 /opt/xmr/index.php