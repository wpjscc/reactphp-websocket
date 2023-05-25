<?php

use Evenement\EventEmitter;

require './vendor/autoload.php';

$socket = new React\Socket\SocketServer('0.0.0.0:9234');

$register = new Register;

// worker 链接
// 定时广播给 server

$register->on('worker_comming', function($connection, $data) {
    Register::$workers->attach($connection, $data);
    Register::broadcastToServerByWorker($connection);
});


// server 链接
$register->on('server_comming', function($connection, $data) {
    Register::$servers->attach($connection, $data);
    Register::broadcastToServer($connection);
});

$register->on('close', function($connection) {

    if (Register::$workers->contains($connection)) {
        Register::$workers->detach($connection);
    }

    if (Register::$servers->contains($connection)) {
        Register::$servers->detach($connection);
    }
});




$socket->on('connection', function (React\Socket\ConnectionInterface $connection) use ($register) {

    $ndjson = new \Clue\React\NDJson\Decoder($connection);
    
    $ndjson->on('data', function ($data) use ($connection, $register) {
        if ($data['cmd'] ?? '') {
            $register->emit($data['cmd'], [$connection, $data['data'] ?? []]);
        }
    });

    $ndjson->on('close', function () use ($connection, $register) {
        $register->emit('close', [$connection]);
    });

});


class Register extends EventEmitter
{
    static $workers = new \SplObjectStorage;
    static $servers = new \SplObjectStorage;

    public static function broadcast()
    {
        foreach (static::$servers as $server) {
            static::broadcastToServer($server);
        }
    }

    public static function broadcastToServer($server, $workers = [])
    {
        $workers = $workers ?: static::$workers;
        foreach ($workers as $worker) {
            $nejson = new \Clue\React\NDJson\Encoder($server);
            // 将worker 广播给server
            $nejson->write([
                'cmd' => 'worker_comming',
                'data' => static::$workers[$worker]
            ]);
        }
    }

    public static function broadcastToServerByWorker($worker)
    {
        foreach (static::$servers as $server) {
            static::broadcastToServer($server, [ 
                $worker 
            ]);
        }
    }
}


\React\EventLoop\Loop::get()->addPeriodicTimer(3, function() {
    Register::broadcast();
});

echo "Register Listen At: http://0.0.0.0:9234\n";
