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
 * This is a player subscription mock file.
 * docker-compose exec xmr sh -c "cd /opt/xmr/tests; php playerSub.php 1234"
 *
 */
require '../vendor/autoload.php';

if (!isset($argv[1])) {
    die('Missing player identity' . PHP_EOL);
}

$identity = $argv[1];

$fp = fopen('key.pem', 'r');
$privateKey = openssl_get_privatekey(fread($fp, 8192));
fclose($fp);

echo 'Sub to: ' . $identity . PHP_EOL;

// Sub
$loop = React\EventLoop\Factory::create();

$context = new React\ZMQ\Context($loop);

$sub = $context->getSocket(ZMQ::SOCKET_SUB);
$sub->connect('tcp://localhost:9505');
$sub->subscribe("H");
$sub->subscribe($identity);

$sub->on('messages', function ($msg) use ($identity, $privateKey) {
    try {
        echo '[' . date('Y-m-d H:i:s') . '] Received: ' . json_encode($msg) . PHP_EOL;

        if ($msg[0] == "H") {
            return;
        }

        // Expect messages to have a length of 3
        if (count($msg) != 3) {
            throw new InvalidArgumentException('Incorrect Message Length');
        }

        // Message will be: channel, key, message
        if ($msg[0] != $identity) {
            throw new InvalidArgumentException('Channel does not match');
        }
    }
    catch (InvalidArgumentException $e) {
        echo $e->getMessage() . PHP_EOL;
    }
});

$loop->run();

openssl_free_key($privateKey);