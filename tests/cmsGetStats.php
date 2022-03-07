<?php
/**
 * Copyright (C) 2022 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 *
 * This is a CMS send MOCK
 *   execute with: docker-compose exec xmr sh -c "cd /opt/xmr/tests; php cmsGetStats.php"
 *
 */
require '../vendor/autoload.php';

try {
    // Create a message and send.
    send('tcp://localhost:50001', 'stats');
} catch (Exception $e) {
    echo $e->getMessage() . PHP_EOL;
}

/**
 * @param $connection
 * @param $message
 * @return bool|string
 * @throws ZMQSocketException
 */
function send($connection, $message)
{
    echo 'Sending to ' . $connection . PHP_EOL;

    // Issue a message payload to XMR.
    $context = new \ZMQContext();

    // Connect to socket
    $socket = new \ZMQSocket($context, \ZMQ::SOCKET_REQ);
    $socket->connect($connection);

    // Send the message to the socket
    $socket->send($message);

    // Need to replace this with a non-blocking recv() with a retry loop
    $retries = 15;
    $reply = false;

    do {
        try {
            // Try and receive
            // if ZMQ::MODE_NOBLOCK/MODE_DONTWAIT is used and the operation would block boolean false
            // shall be returned.
            $reply = $socket->recv(\ZMQ::MODE_DONTWAIT);

            echo 'Received ' . var_export($reply, true) . PHP_EOL;

            if ($reply !== false) {
                break;
            }
        } catch (\ZMQSocketException $sockEx) {
            if ($sockEx->getCode() !== \ZMQ::ERR_EAGAIN) {
                throw $sockEx;
            }
        }

        usleep(100000);

    } while (--$retries);

    // Disconnect socket
    $socket->disconnect($connection);

    return $reply;
}