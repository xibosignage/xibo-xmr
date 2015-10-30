<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (cmsSend.php)
 */
require '../vendor/autoload.php';

$config = json_decode(file_get_contents('../config.json'));

$fp = fopen('key.pub', 'r');
$publicKey = fread($fp, 8192);
fclose($fp);

// Create a message and send.
$reply = (new \Xibo\XMR\CollectNowAction())->setIdentity('player1', $publicKey)->send($config->listenOn);
echo 'Reply received:' . $reply . PHP_EOL;

$reply = (new \Xibo\XMR\CollectNowAction())->setIdentity('unknown', $publicKey)->send($config->listenOn);
echo 'Reply received:' . $reply . PHP_EOL;
