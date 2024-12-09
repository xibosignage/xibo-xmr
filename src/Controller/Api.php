<?php

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
