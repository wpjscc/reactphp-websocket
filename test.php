<?php

require './vendor/autoload.php';

use Ratchet\RFC6455\Messaging\Message;
use Wpjscc\Websocket\WebSocketConnection;
use Wpjscc\Websocket\WebSocketMiddleware;


$server = new \React\Http\HttpServer(
    new WebSocketMiddleware([
        '/websocket'
    ], function (WebSocketConnection $conn) {
        $cliendId = $conn->cliendId;
        echo "connected-{$cliendId}\n";
        $conn->on('message', function (Message $message) use ($conn) {
            echo $message . "\n";
            $conn->send($message);
        });
        $conn->on('close', function ($code, $conn, $reason) {
            echo $code . "\n";
            echo $reason . "\n";
        });
    }),
);

$socket = new \React\Socket\SocketServer('0.0.0.0:8088');
$server->listen($socket);
