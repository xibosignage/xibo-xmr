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

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The tests run inside the XMR image, so check it has what XMR needs.
 */
class ImageTest extends IntegrationTestCase
{
    public static function extensionProvider(): array
    {
        return [['zmq'], ['ev'], ['Zend OPcache']];
    }

    #[DataProvider('extensionProvider')]
    public function testExtensionLoaded(string $extension): void
    {
        $this->assertTrue(extension_loaded($extension), $extension . ' is not loaded');
    }

    public function testOpcacheEnabledForCli(): void
    {
        $this->assertSame('1', ini_get('opcache.enable_cli'));
    }
}
