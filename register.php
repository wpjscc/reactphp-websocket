<?php

$socket = new React\Socket\SocketServer('127.0.0.1:9234');


$socket->on('connection', function (React\Socket\ConnectionInterface $connection) {
   
 
});