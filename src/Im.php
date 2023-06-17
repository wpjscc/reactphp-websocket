<?php

namespace Wpjscc\Websocket;

use Ratchet\RFC6455\Messaging\Message;
use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;

class Im implements EventEmitterInterface
{
    use EventEmitterTrait;

    public function __invoke(WebSocketConnection $conn)
    {
        $conn->on('open', function($conn, $request) {
            $this->onOpen($conn);
            $this->emit('open', [$conn, $request]);
        });

        $conn->on('message', function (Message $message) use ($conn) {
            $this->emit('message', [$conn, $message]);
            $this->onMessage($conn, $message);
        });

        $conn->on('close', function ($code, &$conn, $reason) {
            $this->onClose($conn);
            $this->emit('close', [$conn, $code, $reason]);
            $conn = null;
        });
    }

    public function onOpen(WebSocketConnection $conn)
    {
    
    }

    public function onMessage(WebSocketConnection $from, $msg)
    {

    }

    public function onClose(WebSocketConnection $conn)
    {

    }

    
}