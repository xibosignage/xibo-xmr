#!/bin/sh

#
# Copyright (C) 2024 Xibo Signage Ltd
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
echo '  "ipv6PubSupport": '$XMR_IPV6PUBSUPPORT',' >> /opt/xmr/config.json
echo '  "relayOldMessages": '$XMR_RELAY_OLD_MESSAGES',' >> /opt/xmr/config.json
echo '  "relayMessages": '$XMR_RELAY_MESSAGES >> /opt/xmr/config.json
echo '}' >> /opt/xmr/config.json

/usr/local/bin/php /opt/xmr/index.php