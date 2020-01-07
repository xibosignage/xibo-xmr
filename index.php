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
sequenceDiagram
Player->> CMS: Register
Note right of Player: Register contains the XMR Channel
CMS->> XMR: PlayerAction
XMR->> CMS: ACK
XMR-->> Player: PlayerAction
 *
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

// Decide where to look for the config file
$dirname = (Phar::running(false) == '') ? __DIR__ : dirname(Phar::running(false));
$config = $dirname . '/config.json';

if (!file_exists($config))
    throw new InvalidArgumentException('Missing ' . $config . ' file, please create one in ' . $dirname);

$configString = file_get_contents($config);
$config = json_decode($configString);

if ($config === null)
    throw new InvalidArgumentException('Cannot decode config file ' . json_last_error_msg() . ' config string is [' . $configString . ']');

if ($config->debug)
    $logLevel = \Monolog\Logger::DEBUG;
else
    $logLevel = \Monolog\Logger::WARNING;

// Queue settings
$queuePoll = (property_exists($config, 'queuePoll')) ? $config->queuePoll : 5;
$queueSize = (property_exists($config, 'queueSize')) ? $config->queueSize : 10;

// Set up logging to file
$log = new \Monolog\Logger('xmr');
$log->pushHandler(new \Monolog\Handler\StreamHandler(STDOUT, $logLevel));
$log->info(sprintf('Starting up - listening for CMS on %s.', $config->listenOn));

try {
    $loop = \React\EventLoop\Factory::create();

    /**
     * ZMQ context wraps the PHP implementation.
     * @var \ZMQContext $context
     */
    $context = new React\ZMQ\Context($loop);

    // Reply socket for requests from CMS
    $responder = $context->getSocket(ZMQ::SOCKET_REP);
    $responder->bind($config->listenOn);

    // Set RESP socket options
    if (isset($config->ipv6RespSupport) && $config->ipv6RespSupport === true) {
        $log->debug('RESP MQ Setting socket option for IPv6 to TRUE');
        $responder->setSockOpt(\ZMQ::SOCKOPT_IPV6, true);
    }

    // Pub socket for messages to Players (subs)
    $publisher = $context->getSocket(ZMQ::SOCKET_PUB);

    // Set PUB socket options
    if (isset($config->ipv6PubSupport) && $config->ipv6PubSupport === true) {
        $log->debug('Pub MQ Setting socket option for IPv6 to TRUE');
        $publisher->setSockOpt(\ZMQ::SOCKOPT_IPV6, true);
    }

    foreach ($config->pubOn as $pubOn) {
        $log->info(sprintf('Bind to %s for Publish.', $pubOn));
        $publisher->bind($pubOn);
    }

    // Create an in memory message queue.
    $messageStatsEmpty = [
        'peakQueueSize' => 0,
        'messageCounters' => [
            'total' => 0,
            'sent' => 0,
            'qos1' => 0,
            'qos2' => 0,
            'qos3' => 0,
            'qos4' => 0,
            'qos5' => 0,
            'qos6' => 0,
            'qos7' => 0,
            'qos8' => 0,
            'qos9' => 0,
            'qos10' => 0,
        ]
    ];
    $messageStats = $messageStatsEmpty;
    $messageQueue = [];

    // REP
    $responder->on('error', function ($e) use ($log) {
        $log->error($e->getMessage());
    });

    $responder->on('message', function ($msg) use ($log, $responder, $publisher, &$messageQueue, &$messageStats, $messageStatsEmpty) {

        try {
            // Log incoming message
            $log->info($msg);

            if ($msg === 'stats') {
                // Add the current queue size
                $messageStats['currentQueueSize'] = count($messageQueue);

                // Send response
                $responder->send(json_encode($messageStats));

                // Reset the stats
                $messageStats = $messageStatsEmpty;

            } else {
                // Parse the message and expect a "channel" element
                $msg = json_decode($msg);

                if (!isset($msg->channel))
                    throw new InvalidArgumentException('Missing Channel');

                if (!isset($msg->key))
                    throw new InvalidArgumentException('Missing Key');

                if (!isset($msg->message))
                    throw new InvalidArgumentException('Missing Message');

                // Respond to this message
                $responder->send(true);

                // Make sure QOS is set
                if (!isset($msg->qos)) {
                    // Default to highest priority for messages missing a QOS
                    $msg->qos = 10;
                }

                // Add to stats
                $messageStats['messageCounters']['total']++;
                $messageStats['messageCounters']['qos' . $msg->qos]++;

                // Decide whether we should queue the message or send it immediately.
                if ($msg->qos != 10) {
                    // Queue for the periodic poll to send
                    $log->debug('Queuing');
                    $messageQueue[] = $msg;

                    // Record peak queue
                    $currentQueueSize = count($messageQueue);
                    if ($currentQueueSize > $messageStats['peakQueueSize'])
                        $messageStats['peakQueueSize'] = $currentQueueSize;

                } else {
                    // Send Immediately
                    $log->debug('Sending Immediately');
                    $messageStats['messageCounters']['sent']++;
                    $publisher->sendmulti([$msg->channel, $msg->key, $msg->message]);
                }
            }
        }
        catch (InvalidArgumentException $e) {
            // Return false
            $responder->send(false);

            $log->error($e->getMessage());
        }
    });

    // Queue Processor
    $log->debug('Adding a queue processor for every ' . $queuePoll . ' seconds');
    $loop->addPeriodicTimer($queuePoll, function() use ($log, $publisher, &$messageQueue, $queueSize, &$messageStats) {
        // Is there work to be done
        if (count($messageQueue) > 0) {
            $log->debug('Queue Poll - work to be done.');
            // Order the message queue according to QOS
            usort($messageQueue, function($a, $b) {
                return ($a->qos === $b->qos) ? 0 : ($a->qos < $b->qos) ? -1 : 1;
            });

            // Send up to X messages.
            for ($i = 0; $i < $queueSize; $i++) {
                if ($i > count($messageQueue))
                    break;

                // Pop an element
                $msg = array_pop($messageQueue);

                // Send
                $messageStats['messageCounters']['sent']++;
                $publisher->sendmulti([$msg->channel, $msg->key, $msg->message]);

                $log->debug('Popped ' . $i . ' from the queue, new queue size ' . count($messageQueue));
            }
        }
    });

    // Periodic updater
    $loop->addPeriodicTimer(30, function() use ($log, $publisher) {
        $log->debug('Heartbeat...');
        $publisher->sendmulti(["H", "", ""]);
    });

    // Run the react event loop
    $loop->run();
}
catch (Exception $e) {
    $log->error($e->getMessage());
    $log->error($e->getTraceAsString());
}

// This ends - causing Docker to restart if we're in a container.