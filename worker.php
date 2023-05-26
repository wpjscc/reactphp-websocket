<?php


require './vendor/autoload.php';

use Evenement\EventEmitter;

require './Events.php';

class Worker extends EventEmitter
{
    static $addressToMaster;
    static $addresses = [];

    //  连接到服务端
    public function connectMaster($address)
    {
        // 已经在了
        if (in_array($address, static::$addresses)) {
            return;
        }
        $that = $this;
        $tcpConnector = new React\Socket\TcpConnector();
        $tcpConnector->connect($address)->then(function (React\Socket\ConnectionInterface $connection) use ($that, $address) {
            $that->emit('master_open', [$connection]);
            // 给服务端发送消息
            Worker::write($connection, [
                'event' => 'worker_coming',
                'data' => []
            ]);
        
            $ndjson = new \Clue\React\NDJson\Decoder($connection, true);
            $ndjson->on('data', function ($data) use ($connection, $that) {
                $event = ($data['cmd'] ?? '') ?: ($data['event'] ?? '');
                if ($event) {
                    $that->emit($event, [$connection, $data['data'] ?? []]);
                }
            });
        
            $ndjson->on('close', function () use ($connection, $that) {
                $that->emit('master_close', [$connection]);
            });
            
             // 非本地 和server保持心跳
            if (strpos($address, '127.0.0.1') !== 0) {
                Worker::ping($connection);
            }
        });

    }

    public static function addMaster($connection, $address)
    {
        if ($address) {
            static::$addressToMaster[$address] = $connection;
        }
    }

    public static function removeMaster($connection)
    {
        $address = array_search($connection, static::$addressToMaster);

        if ($address !== false) {
            unset(static::$addressToMaster[$address]);
            if (($key = array_search($address, static::$addresses)) !== false) {
                unset(static::$addresses[$key]);
            }
        }

    }


    public static function write($connection, $data)
    {
        (new \Clue\React\NDJson\Encoder($connection))->write($data);
    }

    public static function ping($connection)
    {
        $timer = null;
        $connection->on('close', function() use (&$timer) {
            if ($timer) {
                \React\EventLoop\Loop::get()->cancelTimer($timer);
            }
        });

        $timer = \React\EventLoop\Loop::get()->addPeriodicTimer(5, function() use ($connection) {
            static::write($connection, [
                'cmd' => 'ping'
            ]);
        });
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

function getParam($key, $default = null) {
    foreach ($GLOBALS['argv'] as $arg) {
        if (strpos($arg, $key) !==false) {
            return explode('=', $arg)[1];
        }
    }
    return $default;
}


$worker = new Worker;

//注册中心回复
$worker->on('register_reply', function($connection, $data) {
    Worker::info('register_reply');
});

// 注册中心广播地址了
$worker->on('broadcast_master_address', function($connection, $data) use ($worker) {
    Worker::info('broadcast_master_address');
    if (isset($data['master_address'])) {
        $worker->connectMaster($data['master_address']);
    }
});

// 注册中心链接关闭了
$worker->on('register_close', function($connection) {
    Worker::info('register_close');
});

// 服务端打开了
$worker->on('master_open', function($connection) {
    Events::onWorkerStart($connection);
    Worker::info('master_open');
});

// 服务端回复了
$worker->on('master_reply', function($connection, $data) {
    Worker::info('master_reply');
    Worker::addMaster($connection, $data['master_address']);
});

// master 连接关闭了
$worker->on('master_close', function($connection) {
    Worker::info('master_close');
    Worker::removeMaster($connection);
});


// master来信息，此时  worker 就可以开始处理了
$worker->on('client_open', function($connection, $data) {

    //todo 业务处理
    Worker::info('client_open');
    Events::onOpen($data['client_id'], $data['data'] ?? []);

});
$worker->on('client_message', function($connection, $data) {
    //todo 业务处理
    Worker::info('client_message');
    Events::onMessage($data['client_id'], $data['message'] ?? '');

});
$worker->on('client_close', function($connection, $data) {
    //todo 业务处理
    Worker::info('client_close');
    Events::onClose($data['client_id']);

});


// 连接接注册中心
$connectRegister = function ($worker, $retrySecond = 3) use (&$connectRegister) {
    $tcpConnector = new React\Socket\TcpConnector();
    $tcpConnector->connect(getParam('--register-address'))->then(function (React\Socket\ConnectionInterface $connection) use ($connectRegister, $worker, $retrySecond) {
        
        // 给注册中心发送消息
        Worker::write($connection, [
            'event' => 'worker_coming',
            'data' => []
        ]);
    
        $ndjson = new \Clue\React\NDJson\Decoder($connection, true);
        $ndjson->on('data', function ($data) use ($connection, $worker) {
            $event = ($data['cmd'] ?? '') ?: ($data['event'] ?? '');
            if ($event) {
                $worker->emit($event, [$connection, $data['data'] ?? []]);
            }
        });
    
        $ndjson->on('close', function () use ($connection, $worker) {
            $worker->emit('register_close', [$connection]);
        });
        // 非本地 和注册中心保持心跳
        if (strpos(getParam('--register-address'), '127.0.0.1') !== 0) {

            Worker::ping($connection);
        }

        $connection->on('close', function() use ($connectRegister, $worker, $retrySecond) {
            Worker::info($retrySecond .' 秒后重新连接');
            \React\EventLoop\Loop::get()->addTimer($retrySecond, function() use ($connectRegister, $worker, $retrySecond) {
                $connectRegister($worker, $retrySecond);
            });
        });
    }, function($e) use ($connectRegister, $worker, $retrySecond) {
        Worker::info($e);
        Worker::info($retrySecond .' 秒后重新连接');
        \React\EventLoop\Loop::get()->addTimer($retrySecond, function() use ($connectRegister, $worker, $retrySecond) {
            $connectRegister($worker, $retrySecond);
        });
    });
};

$connectRegister($worker, 3);
