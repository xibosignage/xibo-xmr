#!/usr/bin/env bash
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

# Build the XMR image, start it and run the test suite against it.
# Arguments are passed to phpunit, e.g. tests/run.sh --testsuite Unit
set -euo pipefail
cd "$(dirname "$0")/.."

NAME=xmr-test

cleanup() {
    docker rm -f "$NAME" >/dev/null 2>&1 || true
    docker network rm "$NAME" >/dev/null 2>&1 || true
}
trap cleanup EXIT
cleanup

docker build -t "$NAME" .
docker build -t "$NAME-runner" --build-arg XMR_IMAGE="$NAME" -f tests/Dockerfile .

docker network create "$NAME" >/dev/null
docker run -d --name "$NAME" --network "$NAME" -e XMR_DEBUG=true -e XMR_QUEUE_POLL=1 "$NAME" >/dev/null

# Wait for XMR to start
for _ in $(seq 1 30); do
    docker logs "$NAME" 2>&1 | grep -q 'Adding a queue processor' && break
    sleep 1
done

status=0
docker run --rm --network "$NAME" -e XMR_HOST="$NAME" "$NAME-runner" vendor/bin/phpunit "$@" || status=$?

if [ "$status" -ne 0 ]; then
    echo "--- XMR logs ---"
    docker logs "$NAME" 2>&1
fi

exit "$status"
