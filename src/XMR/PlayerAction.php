<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (PlayerAction.php)
 */


namespace Xibo\XMR;


abstract class PlayerAction implements PlayerActionInterface
{
    private $channel;
    private $publicKey;

    /**
     * Set the identity of this Player Action
     * @param string $channel
     * @param string $key
     * @return $this
     */
    public final function setIdentity($channel, $key)
    {
        $this->channel = $channel;
        $this->publicKey = openssl_get_publickey($key);
        return $this;
    }

    /**
     * Return the encrypted message and keys
     * @return array
     * @throws PlayerActionException
     */
    public final function getEncryptedMessage()
    {
        $message = null;

        if (!openssl_seal($this->getMessage(), $message, $eKeys, [$this->publicKey]))
            throw new PlayerActionException('Invalid Public Key');

        return [
            'key' => base64_encode($eKeys[0]),
            'message' => base64_encode($message)
        ];
    }

    /**
     * Send the action to the specified connection and wait for a reply (acknowledgement)
     * @param string $connection
     * @return string
     * @throws PlayerActionException
     */
    public final function send($connection)
    {
        try {
            $encrypted = $this->getEncryptedMessage();

            // Envelope our message
            $message = [
                'channel' => $this->channel,
                'message' => $encrypted['message'],
                'key' => $encrypted['key']
            ];

            echo 'Sending: ' . var_export($message, true);

            // Issue a message payload to XMR.
            $requester = new \ZMQSocket(new \ZMQContext(), \ZMQ::SOCKET_REQ);
            $requester->connect($connection);

            $requester->send(json_encode($message));

            return $requester->recv();
        }
        catch (\ZMQSocketException $sockEx) {
            throw new PlayerActionException('XMR connection failed.');
        }
    }
}