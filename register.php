<?php

use Evenement\EventEmitter;

require './vendor/autoload.php';



class Register extends EventEmitter
{
    static $workers;
    static $masters;

    public static function reply($connection)
    {
        static::write($connection, [
            'event' => 'register_reply',
            'data' => []
        ]);
    }

    public static function broadcast()
    {
        foreach (static::$masters as $master) {
            static::broadcastToWorkers($master);
        }
    }

    public static function broadcastToWorkers($master, $workers = [])
    {
        $workers = $workers ?: static::$workers;
        foreach ($workers as $worker) {
            $nejson = new \Clue\React\NDJson\Encoder($worker);
            // 将servet 广播给worker
            $nejson->write([
                'event' => 'broadcast_master_address',
                'data' => static::$masters[$master]
            ]);
        }
    }

    public static function broadcastMasterToWorkerByWorker($worker)
    {
        foreach (static::$masters as $master) {
            static::broadcastToWorkers($master, [ 
                $worker 
            ]);
        }
    }

    public static function write($connection, $data)
    {
        (new \Clue\React\NDJson\Encoder($connection))->write($data);
    }

    public static function pong($connection)
    {
        static::write($connection, [
            'cmd' => 'pong'
        ]);
    }

    public static function info($msg)
    {
        if ($msg instanceof \Exception) {
            echo json_encode([
                'file' => $msg->getFile(),
                'line' => $msg->getLine(),
                'msg' => $msg->getMessage(),
            ]);
        } else {
            echo $msg."\n";
        }
    }
}

Register::$workers = new \SplObjectStorage;
Register::$masters = new \SplObjectStorage;

// \React\EventLoop\Loop::get()->addPeriodicTimer(3, function() {
//     Register::broadcast();
// });




$register = new Register;

// 注册中心打开了(还不知道此时来的是master还是worker)
$register->on('open', function($connection){
    Register::info('open');
});

// worker 来注册了
$register->on('worker_coming', function($connection, $data) {
    Register::info('worker_coming');
    Register::reply($connection);
    Register::$workers->attach($connection, $data);
    Register::broadcastMasterToWorkerByWorker($connection);
});


// master 来注册了
$register->on('master_coming', function($connection, $data) {
    Register::info('master_coming');
    Register::reply($connection);
    Register::$masters->attach($connection, $data);
    Register::broadcastToWorkers($connection);
});

// 心跳
$register->on('ping', function($connection, $data) {
    Register::info('ping');
    Register::pong($connection);
});

// 关闭连接了
$register->on('close', function($connection) {
    if (Register::$workers->contains($connection)) {
        Register::$workers->detach($connection);
        Register::info('worker_close');
    }
    if (Register::$masters->contains($connection)) {
        Register::$masters->detach($connection);
        Register::info('master_close');
    }
});

$socket = new React\Socket\SocketServer('0.0.0.0:9234');

$socket->on('connection', function (React\Socket\ConnectionInterface $connection) use ($register) {
    $register->emit('open', [$connection]);
    $ndjson = new \Clue\React\NDJson\Decoder($connection, true);
    
    $ndjson->on('data', function ($data) use ($connection, $register) {
        $event = ($data['cmd'] ?? '') ?: ($data['event'] ?? '');
        if ($event) {
            $register->emit($event, [$connection, $data['data'] ?? []]);
        }
    });

    $ndjson->on('close', function () use ($connection, $register) {
        $register->emit('close', [$connection]);
    });

    $ndjson->on('error', function ($e)  {
        Register::info($e);
    });

});

echo "Register Listen At: 0.0.0.0:9234\n";
