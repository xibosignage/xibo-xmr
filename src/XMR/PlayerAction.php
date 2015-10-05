<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (PlayerAction.php)
 */


namespace Xibo\XMR;


class PlayerAction
{
    public function sendToCms($connection, $message)
    {
        try {
            // Issue a message payload to XMR.
            $requester = new \ZMQSocket(new \ZMQContext(), \ZMQ::SOCKET_REQ);
            $requester->connect($connection);

            $requester->send($message);

            return $requester->recv();
        }
        catch (\InvalidArgumentException $e) {

        }
        catch (\ZMQSocketException $sockEx) {
            throw new PlayerActionException('XMR connection failed.');
        }
    }
}