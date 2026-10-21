<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
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

namespace Xibo\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Xibo\Entity\Queue;

class QueueTest extends TestCase
{
    public static function missingFieldProvider(): array
    {
        return [
            'channel' => ['channel', 'Missing Channel'],
            'key' => ['key', 'Missing Key'],
            'message' => ['message', 'Missing Message'],
        ];
    }

    #[DataProvider('missingFieldProvider')]
    public function testQueueItemRequiresField(string $field, string $error): void
    {
        $message = ['channel' => 'c', 'key' => 'k', 'message' => 'm'];
        unset($message[$field]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($error);
        (new Queue())->queueItem($message);
    }

    public function testQueueItemDefaults(): void
    {
        $queue = new Queue();
        $queue->queueItem(['channel' => 'c', 'key' => 'k', 'message' => 'm']);

        $this->assertTrue($queue->hasItems());
        $item = $queue->getItem();
        $this->assertSame('c', $item->channel);
        $this->assertSame('k', $item->key);
        $this->assertSame('m', $item->message);
        $this->assertSame(10, $item->qos);
        $this->assertFalse($item->isWebSocket);
        $this->assertFalse($queue->hasItems());
    }

    public function testHighestQosIsSentFirst(): void
    {
        $queue = new Queue();
        foreach ([3, 10, 1, 7] as $qos) {
            $queue->queueItem(['channel' => 'c', 'key' => 'k', 'message' => 'qos' . $qos, 'qos' => $qos]);
        }

        $queue->sortQueue();

        $sent = [];
        while ($queue->hasItems()) {
            $sent[] = $queue->getItem()->qos;
        }
        $this->assertSame([10, 7, 3, 1], $sent);
    }

    public function testFlushStatsResetsCounters(): void
    {
        $queue = new Queue();
        $queue->queueItem(['channel' => 'c', 'key' => 'k', 'message' => 'm', 'qos' => 2]);
        $queue->queueItem(['channel' => 'c', 'key' => 'k', 'message' => 'm', 'qos' => 2]);
        $queue->getItem();

        $stats = $queue->flushStats();
        $this->assertSame(2, $stats['peakQueueSize']);
        $this->assertSame(2, $stats['messageCounters']['total']);
        $this->assertSame(1, $stats['messageCounters']['sent']);
        $this->assertSame(2, $stats['messageCounters']['qos2']);
        $this->assertSame(1, $stats['currentQueueSize']);

        $stats = $queue->flushStats();
        $this->assertSame(0, $stats['peakQueueSize']);
        $this->assertSame(0, $stats['messageCounters']['total']);
        $this->assertSame(1, $stats['currentQueueSize']);
    }

    public function testKeys(): void
    {
        $queue = new Queue();
        $this->assertFalse($queue->authKey('key1'));

        $queue->addKey('http://cms1', 'key1');
        $queue->addKey('http://cms2', 'key2');
        $queue->addKey('http://cms1', 'key1');

        $this->assertTrue($queue->authKey('key1'));
        $this->assertTrue($queue->authKey('key2'));
        $this->assertFalse($queue->authKey('key3'));

        // Nothing has expired yet
        $queue->expireKeys();
        $this->assertTrue($queue->authKey('key1'));
    }
}
