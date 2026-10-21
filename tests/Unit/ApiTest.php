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

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Xibo\Controller\Api;
use Xibo\Controller\Relay;
use Xibo\Entity\Queue;

class ApiTest extends TestCase
{
    private Queue $queue;
    private Api $api;

    protected function setUp(): void
    {
        $this->queue = new Queue();
        $this->api = new Api($this->queue, new NullLogger(), new Relay(new NullLogger(), '', ''));
    }

    public function testStats(): void
    {
        $response = $this->api->handleMessage(['type' => 'stats']);

        $this->assertSame(200, $response->getStatusCode());
        $stats = json_decode((string)$response->getBody(), true);
        $this->assertSame(0, $stats['currentQueueSize']);
        $this->assertArrayHasKey('messageCounters', $stats);
    }

    public function testKeys(): void
    {
        $response = $this->api->handleMessage(['type' => 'keys', 'id' => 'http://cms', 'key' => 'abc']);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertTrue($this->queue->authKey('abc'));
    }

    public function testSingleMessage(): void
    {
        $response = $this->api->handleMessage(['channel' => 'c', 'key' => 'k', 'message' => 'm', 'qos' => 5]);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(1, $this->queue->queueSize());
    }

    public function testMultiMessage(): void
    {
        $response = $this->api->handleMessage([
            'type' => 'multi',
            'messages' => [
                ['channel' => 'c1', 'key' => 'k', 'message' => 'm'],
                ['channel' => 'c2', 'key' => 'k', 'message' => 'm'],
                ['channel' => 'c3', 'key' => 'k', 'message' => 'm', 'isWebSocket' => true],
            ],
        ]);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(3, $this->queue->queueSize());
    }

    public function testInvalidMessageThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->api->handleMessage(['key' => 'k', 'message' => 'm']);
    }
}
