<?php
/*
 * Copyright (C) 2025 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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
 */

namespace Xibo\Controller;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Xibo\Entity\Message;

class Relay
{
    private readonly ?Client $client;
    private ?\ZMQSocket $socket;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $relayMessages,
        private string $relayOldMessages,
    ) {
        // Create a client for us to use
        if (!empty($this->relayMessages) && $this->relayMessages !== 'false') {
            $this->client = new Client([
                'base_uri' => $this->relayMessages,
            ]);
        } else {
            $this->client = null;
        }
    }

    public function configureZmq(): void
    {
        // Create a socket for us to use.
        try {
            $this->socket = (new \ZMQContext())->getSocket(\ZMQ::SOCKET_REQ);
            $this->socket->setSockOpt(\ZMQ::SOCKOPT_LINGER, 2000);
            $this->socket->connect($this->relayOldMessages);
        } catch (\Exception $exception) {
            $this->socket = null;
            $this->relayOldMessages = null;

            $this->logger->critical('Unable to connect to old message relay: '
                . $this->relayOldMessages . ', e = ' . $exception->getMessage());
        }
    }

    public function disconnect(): void
    {
        // If we are connected, then
        if ($this->socket !== null) {
            try {
                $this->socket->disconnect($this->relayOldMessages);
            } catch (\Exception $exception) {
                $this->socket = null;
                $this->relayOldMessages = null;

                $this->logger->critical('Unable to disconnect from old message relay: '
                    . $this->relayOldMessages . ', e = ' . $exception->getMessage());
            }
        }
    }

    private function reconnect(): void
    {
        $this->disconnect();
        $this->configureZmq();
    }

    public function isRelay(): bool
    {
        return !empty($this->relayMessages) && $this->relayMessages !== 'false';
    }

    public function isRelayOld(): bool
    {
        return !empty($this->relayOldMessages) && $this->relayOldMessages !== 'false';
    }

    /**
     * Relay a message appropriately
     * @param \Xibo\Entity\Message $message
     * @return void
     */
    public function relay(Message $message): void
    {
        if ($message->isWebSocket) {
            $this->relayArray($message->jsonSerialize());
        } else {
            try {
                $this->socket->send(json_encode($message));
            } catch (\ZMQSocketException $socketException) {
                $this->logger->error('relay: send [' . $socketException->getCode() . '] ' . $socketException->getMessage());
                if ($socketException->getCode() === 156384763) {
                    // Socket state error, reconnect.
                    // We will drop this message
                    $this->reconnect();
                }
                return;
            }

            $retries = 15;

            do {
                try {
                    $reply = $this->socket->recv(\ZMQ::MODE_DONTWAIT);

                    if ($reply !== false) {
                        break;
                    }
                } catch (\ZMQSocketException $socketException) {
                    $this->logger->error('relay: recv [' . $socketException->getCode() . '] ' . $socketException->getMessage());
                    if ($socketException->getCode() === 156384763) {
                        // Socket state error, reconnect.
                        // We will drop this message
                        $this->reconnect();
                    }
                    break;
                }

                usleep(100000);
            } while (--$retries);
        }
    }

    /**
     * Relay array (only ever a message over private API)
     * @param array $message
     * @return void
     */
    public function relayArray(array $message): void
    {
        try {
            $this->client?->post('/', [
                'json' => $message,
            ]);
        } catch (GuzzleException | \Exception $e) {
            $this->logger->error('relayArray: Unable to relay, e = ' . $e->getMessage());
        }
    }
}