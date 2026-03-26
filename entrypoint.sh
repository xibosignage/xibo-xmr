#!/bin/bash
#
# Copyright (C) 2026 Xibo Signage Ltd
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

set -e

# Write config.json
cat <<EOF > /opt/xmr/config.json
{
  "sockets": {
    "ws": "$XMR_SOCKETS_WS",
    "api": "$XMR_SOCKETS_API",
    "zmq": ["tcp://*:$XMR_SOCKETS_ZM_PORT"]
  },
  "queuePoll": ${XMR_QUEUE_POLL:-5},
  "queueSize": ${XMR_QUEUE_SIZE:-10},
  "debug": ${XMR_DEBUG:-false},
  "ipv6PubSupport": ${XMR_IPV6PUBSUPPORT:-false},
  "relayOldMessages": "${XMR_RELAY_OLD_MESSAGES:-false}",
  "relayMessages": "${XMR_RELAY_MESSAGES:-false}"
}
EOF

chown nobody:nogroup /opt/xmr/config.json

echo "XMR Starting (Memory Limit: $XMR_PHP_MEMORY_LIMIT)..."

# Transition to 'nobody' user and start PHP
# 65534 is the UID/GID for 'nobody'
exec setpriv --reuid=65534 --regid=65534 --clear-groups \
    /usr/local/bin/php -d memory_limit="$XMR_PHP_MEMORY_LIMIT" /opt/xmr/index.php
