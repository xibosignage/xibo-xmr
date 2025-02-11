<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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
 */

// execute with: docker-compose exec xmr sh -c "cd /opt/xmr/tests; php cmsSend.php 1234"
require '../vendor/autoload.php';
$_MESSAGE_COUNT = 15;
$_ENCRYPT = false;

// Track
$start = microtime(true);

if (!isset($argv[1])) {
    die('Missing player identity' . PHP_EOL);
}

$identity = $argv[1];
$isWebSocket = ($argv[2] ?? false) === 'websocket';

// Get the Public Key
$fp = fopen('key.pub', 'r');
$publicKey = openssl_get_publickey(fread($fp, 8192));
fclose($fp);

try {
    //open connection
    $ch = curl_init();

    //set the url, number of POST vars, POST data
    curl_setopt($ch,CURLOPT_URL, 'http://localhost:8081');
    curl_setopt($ch,CURLOPT_POST, true);
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));

    // So that curl_exec returns the contents of the cURL; rather than echoing it
    curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);

    // Queue up a bunch of messages to see what happens
    for ($i = 0; $i < $_MESSAGE_COUNT; $i++) {
        // Reference params
        $message = null;
        $eKeys = null;

        if ($_ENCRYPT) {
            // Encrypt a message
            openssl_seal($i . ' - QOS1', $message, $eKeys, [$publicKey], 'RC4');

            // Create a message and send.
            $fields = [
                'channel' => $identity,
                'key' => base64_encode($eKeys[0]),
                'message' => base64_encode($message),
                'qos' => rand(1, 10),
                'isWebSocket' => $isWebSocket,
            ];
        } else {
            $fields = [
                'channel' => $identity,
                'key' => 'key',
                'message' => 'message ' . $i,
                'qos' => rand(1, 10),
                'isWebSocket' => $isWebSocket,
            ];
        }


        curl_setopt($ch,CURLOPT_POSTFIELDS, json_encode($fields));

        //execute post
        $result = curl_exec($ch);
        echo $result . PHP_EOL;

        usleep(50);
    }
} catch (Exception $e) {
    echo $e->getMessage() . PHP_EOL;
}

$end = microtime(true);
echo PHP_EOL . 'Duration: ' . ($end - $start) . ', Start: ' . $start . ', End: ' . $end . PHP_EOL;

/**
 * @param $socket
 * @param $message
 * @return bool|string
 * @throws ZMQSocketException
 */
function send($socket, $message)
{
    // Send the message to the socket
    $socket->send(json_encode($message));

    // Need to replace this with a non-blocking recv() with a retry loop
    $retries = 15;
    $reply = false;

    do {
        try {
            // Try and receive
            // if ZMQ::MODE_NOBLOCK/MODE_DONTWAIT is used and the operation would block boolean false
            // shall be returned.
            $reply = $socket->recv(\ZMQ::MODE_DONTWAIT);

            if ($reply !== false)
                break;

            echo '.';
        } catch (\ZMQSocketException $sockEx) {
            if ($sockEx->getCode() !== \ZMQ::ERR_EAGAIN)
                throw $sockEx;
        }

        usleep(100000);

    } while (--$retries);

    return $reply;
}
