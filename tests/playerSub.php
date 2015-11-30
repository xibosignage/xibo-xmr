<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (playerSub.php)
 */
require '../vendor/autoload.php';

if (!isset($argv[1]))
    die('Missing player identity' . PHP_EOL);

$identity = $argv[1];

$config = json_decode(file_get_contents('../config.json'));

echo 'Connecting to: ' . $config->pubOn[0] . PHP_EOL;

$fp = fopen('key.pem', 'r');
$privateKey = openssl_get_privatekey(fread($fp, 8192));
fclose($fp);

// Sub
$loop = React\EventLoop\Factory::create();

$context = new React\ZMQ\Context($loop);

$sub = $context->getSocket(ZMQ::SOCKET_SUB);
$sub->connect($config->pubOn[0]);
$sub->subscribe("H");
$sub->subscribe($identity);

$sub->on('messages', function ($msg) use ($identity, $privateKey) {
    try {
        echo 'Received: ' . json_encode($msg) . PHP_EOL;

        if ($msg[0] == "H")
            return;

        // Expect messages to have a length of 3
        if (count($msg) != 3)
            throw new InvalidArgumentException('Incorrect Message Length');

        // Message will be channel, key, message
        if ($msg[0] != $identity)
            throw new InvalidArgumentException('Channel does not match');

        // Decrypt the message using our private key
        $opened = null;

        $key = base64_decode($msg[1]);
        $message = base64_decode($msg[2]);

        if (!openssl_open($message, $opened, $key, $privateKey))
            throw new \Xibo\XMR\PlayerActionException('Encryption Error');

        echo 'Message: ' . $opened;
    }
    catch (InvalidArgumentException $e) {
        echo $e->getMessage();
    }
});

$loop->run();

openssl_free_key($privateKey);