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

namespace Xibo\Entity;

class Queue
{
    private array $instances = [];

    /** @var \Xibo\Entity\Message[]  */
    private array $queue;

    private array $stats;

    public function __construct()
    {
        $this->queue = [];
        $this->stats = [
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

    }

    public function hasItems(): bool
    {
        return count($this->queue);
    }

    public function queueSize(): int
    {
        return count($this->queue);
    }

    public function sortQueue(): void
    {
        // Order the message queue according to QOS
        usort($this->queue, function($a, $b) {
            return ($a->qos === $b->qos) ? 0 : (($a->qos < $b->qos) ? -1 : 1);
        });
    }

    public function getItem(): Message
    {
        $this->stats['messageCounters']['sent']++;

        return array_pop($this->queue);
    }

    /**
     * @param array $message
     * @return void
     * @throws \InvalidArgumentException
     */
    public function queueItem(array $message): void
    {
        $msg = new Message();

        if (!isset($message['channel'])) {
            throw new \InvalidArgumentException('Missing Channel');
        }

        if (!isset($message['key'])) {
            throw new \InvalidArgumentException('Missing Key');
        }

        if (!isset($message['message'])) {
            throw new \InvalidArgumentException('Missing Message');
        }

        // Make sure QOS is set
        if (!isset($message['qos'])) {
            // Default to the highest priority for messages missing a QOS
            $message['qos'] = 10;
        }

        $msg->channel = $message['channel'];
        $msg->key = $message['key'];
        $msg->message = $message['message'];
        $msg->qos = $message['qos'];
        $msg->isWebSocket = $message['isWebSocket'] ?? false;

        // Queue
        $this->queue[] = $msg;

        // Update stats
        $this->stats['messageCounters']['total']++;
        $this->stats['messageCounters']['qos' . $msg->qos]++;

        $currentQueueSize = $this->queueSize();
        if ($currentQueueSize > $this->stats['peakQueueSize']) {
            $this->stats['peakQueueSize'] = $currentQueueSize;
        }
    }

    public function flushStats(): array
    {
        $stats = $this->stats;
        $stats['currentQueueSize'] = $this->queueSize();
        $this->clearStats();
        return $stats;
    }

    private function clearStats(): void
    {
        $this->stats = [
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
    }

    public function addKey(string $instance, string $key): void
    {
        if (!array_key_exists($instance, $this->instances)) {
            $this->instances[$instance] = ['keys' => []];
        }
        $this->instances[$instance]['keys'][] = [
            'key' => $key,
            'expires' => time() + 86400,
        ];
    }

    public function authKey(string $providedKey): bool
    {
        foreach ($this->instances as $instance) {
            foreach ($instance['keys'] as $key) {
                if ($key['key'] === $providedKey && time() < $key['expires']) {
                    return true;
                }
            }
        }

        return false;
    }

    public function expireKeys(): void
    {
        // Expire keys within each instance
        foreach ($this->instances as $instanceKey => $instance) {
            foreach ($instance['keys'] as $key => $value) {
                // Expire any keys which are no longer in date.
                if (time() >= $value['expires']) {
                    unset($instance['keys'][$key]);
                }
            }

            // Remove instances with no keys
            if (count($instance['keys']) <= 0) {
                unset($this->instances[$instanceKey]);
            }
        }
    }
}
