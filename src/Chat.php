<?php

namespace Wpjscc\Websocket;

use Ratchet\RFC6455\Messaging\Message;

class Chat
{
    public $clients;

    public function __construct() 
    {
        $this->clients = new \SplObjectStorage;
    }
    
    public function __invoke(WebSocketConnection $conn)
    {
        $conn->on('open', function($conn) {
            $conn->send($conn->cliend_id);
            $this->onOpen($conn);
        });

        $conn->on('message', function (Message $message) use ($conn) {
            $this->onMessage($conn, $message);
        });
        $conn->on('close', function ($code, $conn, $reason) {
            $this->onClose($conn);
        });
    }

    public function onOpen(WebSocketConnection $conn)
    {
        $this->clients->attach($conn);
    }

    public function onMessage(WebSocketConnection $from, $msg)
    {
        foreach ($this->clients as $client) {
            if ($from !== $client) {
                echo $msg."\n";
                $client->send($msg);
            }
        }
    }

    public function onClose(WebSocketConnection $conn)
    {
        $this->clients->detach($conn);
    }

    
}