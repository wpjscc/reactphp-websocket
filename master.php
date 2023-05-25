<?php

$socket = new React\Socket\SocketServer('127.0.0.1:8090');


$socket->on('connection', function (React\Socket\ConnectionInterface $connection) {
   
 
 });