<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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

use Psr\Log\LoggerInterface;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Xibo\Entity\Display;
use Xibo\Entity\Queue;
use XiboSignage\Client\Client;

class Server implements MessageComponentInterface
{
    /** @var Display[] */
    private array $displays = [];
    private array $ids = [];

    public function __construct(
        private readonly Queue $queue,
        private readonly LoggerInterface $logger
    ) {
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->logger->debug('onOpen: ' . $conn->resourceId);

        $this->addDisplay(
            $conn->resourceId,
            $conn
        );
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $this->removeDisplay($conn->resourceId);
        $this->logger->debug('onClose: ' . $conn->resourceId);
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        $this->logger->debug('onError: ' . $conn->resourceId . ', e: ' . $e->getMessage());
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $display = $this->getDisplayByResourceId($from->resourceId);

        $this->logger->debug('onMessage: ' . $display->resourceId);

        // Expect a JSON string
        $json = json_decode($msg, true);
        if ($json === null) {
            $this->logger->error('onMessage: Invalid JSON');
            return;
        }

        // We are only expecting one message, which initialises the connection.
        try {
            if (($json['type'] ?? 'empty') === 'init') {
                // The display should pass us a key
                $key = $json['key'] ?? null;
                if (empty($key)) {
                    throw new \InvalidArgumentException('Missing key');
                }

                $channel = $json['channel'] ?? null;
                if (empty($channel)) {
                    throw new \InvalidArgumentException('Missing channel');
                }

                // Validate the key provided
                if (!$this->queue->authKey($key)) {
                    throw new \InvalidArgumentException('Invalid key');
                }

                // Valid key for the CMS
                $this->linkDisplay($display, $channel);
            } else {
                throw new \Exception('Invalid message type');
            }
        } catch (\Exception $e) {
            $this->logger->error('onMessage: ' . $e->getMessage());

            // Close the socket with an error (onClose gets called to remove the connection)
            $display->connection->close();
        }
    }

    public function heartbeat(): void
    {
        foreach ($this->displays as $display) {
            if ($display->id !== null) {
                $display->connection->send('H');
            }
        }
    }

    /**
     * Add a display to the list of connections (unauthed at this point)
     * @param string $resourceId
     * @param \Ratchet\ConnectionInterface $connection
     * @return \Xibo\Entity\Display
     */
    private function addDisplay(string $resourceId, ConnectionInterface $connection): Display
    {
        $this->displays[$resourceId] = new Display($resourceId, $connection);
        return $this->displays[$resourceId];
    }

    /**
     * Link a display to an ID (which is the channel)
     * @param \Xibo\Entity\Display $display
     * @param string $id
     * @return void
     */
    private function linkDisplay(Display $display, string $id): void
    {
        // Make a pointer between this resource and the ID
        $this->ids[$id] = $display->resourceId;
        $display->id = $id;
    }

    /**
     * Remove a display
     * @param string $resourceId
     * @return void
     */
    private function removeDisplay(string $resourceId): void
    {
        $display = $this->getDisplayByResourceId($resourceId);
        if ($display !== null && $display->id !== null) {
            unset($this->ids[$display->id]);
        }
        unset($this->displays[$resourceId]);
    }

    /**
     * Get a display by its ID (channel)
     * @param string $id
     * @return \Xibo\Entity\Display|null
     */
    public function getDisplayById(string $id): ?Display
    {
        if (isset($this->ids[$id])) {
            return $this->displays[$this->ids[$id]] ?? null;
        } else {
            return null;
        }
    }

    /**
     * Get a display by its socket resource
     * @param string $resourceId
     * @return \Xibo\Entity\Display|null
     */
    private function getDisplayByResourceId(string $resourceId): ?Display
    {
        return $this->displays[$resourceId] ?? null;
    }
}
