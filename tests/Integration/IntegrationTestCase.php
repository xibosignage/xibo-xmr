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

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * Tests against a running XMR, whose host is given in XMR_HOST (see tests/run.sh)
 */
abstract class IntegrationTestCase extends TestCase
{
    protected string $host;

    protected function setUp(): void
    {
        $host = getenv('XMR_HOST');
        if (empty($host)) {
            $this->markTestSkipped('XMR_HOST not set, run with tests/run.sh');
        }
        $this->host = $host;
    }

    /**
     * POST a message to the private API, as the CMS would
     */
    protected function api(array|string $body, string $method = 'POST'): ResponseInterface
    {
        return (new Client(['http_errors' => false, 'timeout' => 5]))->request(
            $method,
            'http://' . $this->host . ':8081',
            [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => is_array($body) ? json_encode($body) : $body,
            ]
        );
    }

    protected function stats(): array
    {
        return json_decode((string)$this->api(['type' => 'stats'])->getBody(), true);
    }
}
