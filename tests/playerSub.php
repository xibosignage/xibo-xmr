<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (playerSub.php)
 */
require '../vendor/autoload.php';

$config = json_decode(file_get_contents('../config.json'));

echo 'Binding to: ' . $config->pubOn . PHP_EOL;

// Sub
$loop = React\EventLoop\Factory::create();

$context = new React\ZMQ\Context($loop);

$sub = $context->getSocket(ZMQ::SOCKET_SUB);
$sub->bind($config->pubOn);
$sub->subscribe('cms');

$sub->on('messages', function ($msg) {
    echo "Received: " . json_encode($msg) . "\n";
});

$loop->run();