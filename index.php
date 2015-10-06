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
$log->info(sprintf('Starting up - listening on %s, publishing on %s.', $config->listenOn, $config->pubOn));

try {
    $loop = React\EventLoop\Factory::create();

    $context = new React\ZMQ\Context($loop);

    // Reply socket for requests from CMS
    $responder = $context->getSocket(ZMQ::SOCKET_REP);
    $responder->bind($config->listenOn);

    // Pub socket for messages to Players (subs)
    $publisher = $context->getSocket(ZMQ::SOCKET_PUB);
    $publisher->connect($config->pubOn);

    // REP
    $responder->on('error', function ($e) {
        var_dump($e->getMessage());
    });

    $responder->on('message', function ($msg) use ($log, $responder, $publisher) {

        $log->info($msg);

        // Do something
        sleep(2);

        // Respond to this message
        $responder->send(true);

        // Push message out to subscribers
        $log->info('Sending: ' . $msg);
        $publisher->sendmulti(['cms', $msg]);
        //$publisher->send('cms ' . $msg);
    });

    // Run the react event loop
    $loop->run();
}
catch (Exception $e) {
    $log->error($e->getMessage());
    $log->error($e->getTraceAsString());
}