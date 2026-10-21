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

namespace Xibo\Tests\Integration;

class ApiTest extends IntegrationTestCase
{
    public function testGetNotAllowed(): void
    {
        $response = $this->api('', 'GET');

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('Method not allowed', (string)$response->getBody());
    }

    public function testInvalidJson(): void
    {
        $response = $this->api('not json');

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('Not valid JSON', (string)$response->getBody());
    }

    public function testInvalidMessage(): void
    {
        $response = $this->api(['key' => 'k', 'message' => 'm']);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('Missing Channel', (string)$response->getBody());
    }

    public function testKeys(): void
    {
        $response = $this->api(['type' => 'keys', 'id' => 'http://cms', 'key' => uniqid()]);

        $this->assertSame(201, $response->getStatusCode());
    }

    public function testQueuedMessagesAppearInStats(): void
    {
        // Stats are reset each time they are read
        $this->stats();

        $this->assertSame(201, $this->api([
            'channel' => uniqid('api'),
            'key' => 'k',
            'message' => 'm',
            'qos' => 4,
        ])->getStatusCode());

        $this->assertSame(201, $this->api([
            'type' => 'multi',
            'messages' => [
                ['channel' => uniqid('api'), 'key' => 'k', 'message' => 'm', 'qos' => 4],
                ['channel' => uniqid('api'), 'key' => 'k', 'message' => 'm', 'qos' => 9],
            ],
        ])->getStatusCode());

        $stats = $this->stats();
        $this->assertSame(3, $stats['messageCounters']['total']);
        $this->assertSame(2, $stats['messageCounters']['qos4']);
        $this->assertSame(1, $stats['messageCounters']['qos9']);
    }
}
