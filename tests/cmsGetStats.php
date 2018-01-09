<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015-18 Spring Signage Ltd
 * (cmsSend.php)
 *
 * This is a CMS send MOCK
 *   execute with: docker exec -it xiboxmr_xmr_1 sh -c "cd /opt/xmr/tests; php cmsSend.php 1234"
 *
 */
require '../vendor/autoload.php';

if (!isset($argv[1]))
    die('Missing player identity' . PHP_EOL);

$identity = $argv[1];

// Use the same settings as the running XMR instance
$config = json_decode(file_get_contents('../config.json'));

try {
    // Create a message and send.
    send($config->listenOn, 'stats');

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

            if ($reply !== false)
                break;

        } catch (\ZMQSocketException $sockEx) {
            if ($sockEx->getCode() !== \ZMQ::ERR_EAGAIN)
                throw $sockEx;
        }

        usleep(100000);

    } while (--$retries);

    // Disconnect socket
    //$socket->disconnect($connection);

    return $reply;
}