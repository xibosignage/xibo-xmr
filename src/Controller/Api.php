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
use React\Http\Message\Response;
use Xibo\Entity\Queue;

class Api
{
    public function __construct(
        private readonly Queue $queue,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Handle messages hitting the API
     * @param array $message
     * @return \React\Http\Message\Response
     */
    public function handleMessage(array $message): Response
    {
        $type = $message['type'] ?? 'empty';

        $this->logger->debug('handleMessage: type = ' . $type);

        if ($type === 'stats') {
            // Success
            return Response::json($this->queue->flushStats());
        } else if ($type === 'keys') {
            // Register new keys for this CMS.
            $this->queue->addKey($message['id'], $message['key']);
        } else if ($type === 'multi') {
            $this->logger->debug('Queuing multiple messages');
            foreach ($message['messages'] as $message) {
                $this->queue->queueItem($message);
            }
        } else {
            $this->logger->debug('Queuing');
            $this->queue->queueItem($message);
        }

        // Success
        return new Response(201);
    }
}
