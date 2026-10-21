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

use WebSocket\Client;
use WebSocket\Message\Close;
use WebSocket\Message\Text;

/**
 * Messages from the CMS delivered to a display over a web socket
 */
class WebSocketTest extends IntegrationTestCase
{
    private function connect(): Client
    {
        $client = new Client('ws://' . $this->host . ':8080');
        $client->setTimeout(5);
        $client->connect();
        return $client;
    }

    /**
     * Receive the next text message, skipping heartbeats
     */
    private function receiveText(Client $client): string
    {
        do {
            $message = $client->receive();
        } while (!$message instanceof Text || $message->getContent() === 'H');

        return $message->getContent();
    }

    public function testMessageDelivered(): void
    {
        $key = uniqid('key');
        $channel = uniqid('display');
        $this->assertSame(201, $this->api(['type' => 'keys', 'id' => 'http://cms', 'key' => $key])->getStatusCode());

        $client = $this->connect();
        $client->text(json_encode(['type' => 'init', 'key' => $key, 'channel' => $channel]));

        $this->assertSame(201, $this->api([
            'channel' => $channel,
            'key' => 'k',
            'message' => 'hello ' . $channel,
            'qos' => 10,
            'isWebSocket' => true,
        ])->getStatusCode());

        $this->assertSame('hello ' . $channel, $this->receiveText($client));
        $client->close();
    }

    public function testInvalidKeyClosesConnection(): void
    {
        $client = $this->connect();
        $client->text(json_encode(['type' => 'init', 'key' => 'not-registered', 'channel' => uniqid('display')]));

        do {
            $message = $client->receive();
        } while ($message instanceof Text && $message->getContent() === 'H');

        $this->assertInstanceOf(Close::class, $message);
    }
}
