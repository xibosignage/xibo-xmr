<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (cmsSend.php)
 */
require '../vendor/autoload.php';

$config = json_decode(file_get_contents('../config.json'));

// Create a message and send.
$reply = (new \Xibo\XMR\PlayerAction())->sendToCms($config->listenOn, json_encode(['test']));

echo 'Reply received:' . $reply . PHP_EOL;