#!/usr/bin/env php
<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (listen.php)
 */
require 'vendor/autoload.php';

function exception_error_handler($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        // This error code is not included in error_reporting
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
}
set_error_handler("exception_error_handler");

if (!file_exists('config.json'))
    throw new InvalidArgumentException('Missing config.json file, please create one in the same folder as the application');

$config = json_decode(file_get_contents('config.json'));

if ($config->debug)
    $logLevel = \Monolog\Logger::DEBUG;
else
    $logLevel = \Monolog\Logger::WARNING;

// Set up logging to file
$log = new \Monolog\Logger('xmr');
$log->pushHandler(new \Monolog\Handler\StreamHandler('log.txt', $logLevel));
$log->pushHandler(new \Monolog\Handler\StreamHandler(STDOUT, $logLevel));
$log->info('Starting up, listening on ' . $config->listenOn);

try {
    $context = new React\ZMQ\Context($loop);

    $factory = new \React\Datagram\Factory($loop);

    $pull = $context->getSocket(ZMQ::SOCKET_PULL);
    $pull->bind('tcp://127.0.0.1:5555');

    $pull->on('error', function ($e) {
        var_dump($e->getMessage());
    });

    $pull->on('message', function ($msg) {
        echo "Received: $msg\n";
    });

    $loop->run();
}
catch (Exception $e) {
    $log->error($e->getMessage());
    $log->error($e->getTraceAsString());
}