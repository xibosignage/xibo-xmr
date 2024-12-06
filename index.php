#!/usr/bin/env php
<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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
 */

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use React\Http\Message\Response;
use Xibo\Controller\Api;
use Xibo\Controller\Server;
use Xibo\Entity\Queue;

require 'vendor/autoload.php';

// TODO: ratchet does not support PHP8
error_reporting(E_ALL ^ E_DEPRECATED);

set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        // This error code is not included in error_reporting
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Decide where to look for the config file
$dirname = (Phar::running(false) == '') ? __DIR__ : dirname(Phar::running(false));
$config = $dirname . '/config.json';

if (!file_exists($config)) {
    throw new InvalidArgumentException('Missing ' . $config . ' file, please create one in ' . $dirname);
}

$configString = file_get_contents($config);
$config = json_decode($configString);

if ($config === null) {
    throw new InvalidArgumentException('Cannot decode config file ' . json_last_error_msg() . ' config string is [' . $configString . ']');
}

$logLevel = $config->debug ? Logger::DEBUG : Logger::WARNING;

// Set up logging to file
$log = new Logger('xmr');
$log->pushHandler(new StreamHandler(STDOUT, $logLevel));

// Queue settings
$queuePoll = (property_exists($config, 'queuePoll')) ? $config->queuePoll : 5;
$queueSize = (property_exists($config, 'queueSize')) ? $config->queueSize : 10;

// Create an in memory message queue.
$messageQueue = new Queue();

try {
    $loop = Loop::get();

    // Web Socket server
    $messagingServer = new Server($messageQueue, $log);
    $wsSocket = new React\Socket\SocketServer($config->sockets->ws);
    $wsServer = new WsServer($messagingServer);
    $ioServer = new IoServer(
        new HttpServer($wsServer),
        $wsSocket,
        $loop
    );

    // Enable keep alive
    $wsServer->enableKeepAlive($ioServer->loop);

    $log->info('WS listening on ' . $config->sockets->ws);

    // LEGACY: Pub socket for messages to Players (subs)
    $publisher = (new React\ZMQ\Context($loop))->getSocket(ZMQ::SOCKET_PUB);

    // Set PUB socket options
    if (isset($config->ipv6PubSupport) && $config->ipv6PubSupport === true) {
        $log->debug('Pub MQ Setting socket option for IPv6 to TRUE');
        $publisher->setSockOpt(\ZMQ::SOCKOPT_IPV6, true);
    }

    foreach ($config->sockets->zmq as $pubOn) {
        $log->info(sprintf('Bind to %s for Publish.', $pubOn));
        $publisher->bind($pubOn);
    }

    // Create a private API to receive messages from the CMS
    $api = new Api($messageQueue, $log);

    // Create a HTTP server to handle requests to the API
    $http = new React\Http\HttpServer(function (Psr\Http\Message\ServerRequestInterface $request) use ($log, $api) {
        try {
            if ($request->getMethod() !== 'POST') {
                throw new Exception('Method not allowed');
            }

            $json = json_decode($request->getBody()->getContents(), true);
            if ($json === false || !is_array($json)) {
                throw new InvalidArgumentException('Not valid JSON');
            }

            return $api->handleMessage($json);
        } catch (Exception $e) {
            $log->error('API: e = ' . $e->getMessage());
            return new Response(
                422,
                ['Content-Type' => 'plain/text'],
                $e->getMessage()
            );
        }
    });
    $socket = new React\Socket\SocketServer($config->sockets->api);
    $http->listen($socket);
    $http->on('error', function (Exception $exception) use ($log) {
        $log->error('http: ' . $exception->getMessage());
        $log->debug('stack: ' . $exception->getTraceAsString());
    });

    $log->info('HTTP listening');

    // Queue Processor
    $log->debug('Adding a queue processor for every ' . $queuePoll . ' seconds');
    $loop->addPeriodicTimer($queuePoll, function() use ($log, $messagingServer, $publisher, $messageQueue, $queueSize) {
        // Is there work to be done
        if ($messageQueue->hasItems()) {
            $log->debug('Queue Poll - work to be done.');

            $messageQueue->sortQueue();

            $log->debug('Queue Poll - message queue sorted');

            // Send up to X messages.
            for ($i = 0; $i < $queueSize; $i++) {
                if ($i > $messageQueue->queueSize()) {
                    $log->debug('Queue Poll - queue size reached');
                    break;
                }

                // Pop an element
                $msg = $messageQueue->getItem();

                // Send
                $log->debug('Sending ' . $i);

                // Where are we sending this item?
                if ($msg->isWebSocket) {
                    $display = $messagingServer->getDisplayById($msg->channel);
                    if ($display === null) {
                        $log->info('Display ' . $msg->channel . ' not connected');
                        continue;
                    }
                    $display->connection->send(json_encode($msg));
                } else {
                    $publisher->sendmulti([$msg->channel, $msg->key, $msg->message], \ZMQ::MODE_DONTWAIT);
                }

                $log->debug('Popped ' . $i . ' from the queue, new queue size ' . $messageQueue->queueSize());
            }
        }
    });

    // Periodic updater
    $loop->addPeriodicTimer(30, function() use ($log, $messagingServer, $publisher) {
        $log->debug('Heartbeat...');

        // Send to all connected WS clients
        $messagingServer->heartbeat();

        // Send to PUB queue
        $publisher->sendmulti(["H", "", ""], \ZMQ::MODE_DONTWAIT);
    });

    // Run the React event loop
    $loop->run();
} catch (Exception $e) {
    $log->error($e->getMessage());
    $log->error($e->getTraceAsString());
}

// This ends - causing Docker to restart if we're in a container.