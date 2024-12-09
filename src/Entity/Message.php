<?php

namespace Xibo\Entity;

class Message
{
    public string $channel;
    public string $key;
    public string $message;
    public int $qos;
    public bool $isWebSocket;
}
