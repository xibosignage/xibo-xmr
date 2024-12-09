<?php

namespace Xibo\Entity;

use Ratchet\ConnectionInterface;

class Display
{
    public ?string $id = null;

    public function __construct(
        public string $resourceId,
        public ConnectionInterface $connection
    ) {
    }
}
