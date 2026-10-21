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

/**
 * Messages from the CMS delivered to a (legacy) display over the ZMQ pub socket
 */
class ZmqTest extends IntegrationTestCase
{
    public function testMessageDelivered(): void
    {
        $channel = uniqid('display');

        $sub = (new \ZMQContext())->getSocket(\ZMQ::SOCKET_SUB);
        $sub->setSockOpt(\ZMQ::SOCKOPT_RCVTIMEO, 5000);
        $sub->connect('tcp://' . $this->host . ':9505');
        $sub->setSockOpt(\ZMQ::SOCKOPT_SUBSCRIBE, $channel);

        // Give the subscription time to reach the publisher, or the message is dropped
        usleep(500000);

        $this->assertSame(201, $this->api([
            'channel' => $channel,
            'key' => 'key',
            'message' => 'hello',
            'qos' => 10,
        ])->getStatusCode());

        $this->assertSame([$channel, 'key', 'hello'], $sub->recvMulti());
    }
}
