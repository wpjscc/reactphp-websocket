<?php

require './vendor/autoload.php';

use Evenement\EventEmitter;


class Master extends EventEmitter
{
    static $workers = [];

    public static $clients;
    public static $client_id_to_client = [];

    public function __construct()
    {
        static::$clients = new \SplObjectStorage;
    }


    public static function replyWorker($connection)
    {
        static::write($connection, [
            'event' => 'master_reply',
            'data' => [
                'master_address' => getParam('--master-address')
            ]
        ]);
    }

    public static function addWorker($connection)
    {
        if (array_search($connection, static::$workers) === false) {
            static::$workers[] = $connection;
        }
    }

    public static function removeWorker($connection)
    {
        if (($key = array_search($connection, static::$workers)) !== false) {
            unset(static::$workers[$key]);
        }
    }

    public static function getWorker($connection)
    {
        if (isset($connection->_worker)) {
            return $connection->_worker;
        }

        if (count(static::$workers)>0) {
            $connection->_worker = static::$workers[array_rand(static::$workers)];
            $connection->_worker->on('close', function() use ($connection) {
                unset($connection->_worker);
            });
            return $connection->_worker;
        }
        return ;
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
        $timer = \React\EventLoop\Loop::get()->addPeriodicTimer(30, function() use ($connection) {
            static::write($connection, [
                'cmd' => 'ping'
            ]);
        });
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


function getParam($key, $default = null) {
    foreach ($GLOBALS['argv'] as $arg) {
        if (strpos($arg, $key) !==false) {
            return explode('=', $arg)[1];
        }
    }
    return $default;
}


$master = new Master;

//注册中心打开了
$master->on('register_open', function($connection) {
    Master::info('register_open');

});
//注册中心回复
$master->on('register_reply', function($connection, $data) {
    Master::info('register_reply');
});
// 注册中心连接关闭了
$master->on('register_close', function($connection) {
    Master::info('register_close');

});

//worker 连接打开了
$master->on('worker_open', function($connection) {
    Master::info('worker_open');
});
// worker 来了
$master->on('worker_coming', function($connection, $data) {
    Master::info('worker_coming');
    Master::addWorker($connection);
    Master::replyWorker($connection);
});

// worker 关闭了
$master->on('worker_close', function($connection) {
    Master::removeWorker($connection);
    Master::info('worker_close');
});

// worker ping
$master->on('ping', function($connection, $data) {
    Master::info('worker_ping');
    Master::pong($connection);
});


// 以下是client 相关
// connection 是客户端的链接
// 客户端链接打开
$master->on('client_open', function($connection, $data) {
    $worker = Master::getWorker($connection);

    if ($worker) {
        Master::write($worker, [
            'event' => 'client_open',
            'data' => [
                'client_id' => $connection->client_id,
                'data' => $data
            ]
        ]);
    }
    Master::$clients->attach($connection);
    Master::$client_id_to_client[$connection->client_id] = $connection;
});

// 客户端收到信息
$master->on('client_message', function($connection, $msg) {
    $worker = Master::getWorker($connection);
    if ($worker) {

        Master::write($worker, [
            'event' => 'client_message',
            'data' => [
                'client_id' => $connection->client_id,
                'message' => $msg
            ]
        ]);
    }

});

// 客户端关闭
$master->on('client_close', function($connection) {
    if (isset($connection->_worker)) {
        Master::write($connection->_worker, [
            'event' => 'client_close',
            'data' => [
                'client_id' => $connection->client_id,
                'message' => ''
            ]
        ]);
    }
    // 清除链接信息
    Master::$clients->detach($connection);
    unset(Master::$client_id_to_client[$connection->client_id]);
});

// 收到worker信息
$master->on('worker_message', function($connection, $data) {
    $client_id = $data['client_id'] ?? '';
    if ($client_id) {
        $client = Master::$client_id_to_client[$client_id] ?? null;
        if ($client) {
            $client->write($data);
        }
    }
});


// 连接接注册中心
$connectRegister = function($master, $retrySecond = 3) use (&$connectRegister) {
    $tcpConnector = new React\Socket\TcpConnector();
    $tcpConnector->connect(getParam('--register-address'))->then(function (React\Socket\ConnectionInterface $connection) use ($connectRegister, $retrySecond, $master) {
        $master->emit('register_open', [$connection]);
        // 给注册中心发送消息，里面的data 会转发给 worker
        Master::write($connection, [
            'event' => 'master_coming',
            'data' => [
                'master_address' => getParam('--master-address')
            ]
        ]);

        $ndjson = new \Clue\React\NDJson\Decoder($connection, true);
        $ndjson->on('data', function ($data) use ($connection, $master) {
            $event = ($data['cmd'] ?? '') ?: ($data['event'] ?? '');
            if ($event) {
                $master->emit($event, [$connection, $data['data'] ?? []]);
            }
        });

        $ndjson->on('close', function () use ($connection, $master) {
            $master->emit('register_close', [$connection]);
        });

        // 非本地 和注册中心保持心跳
        if (strpos(getParam('--register-address'), '127.0.0.1') !== 0) {
            Master::ping($connection);
        }
        
        $connection->on('close', function() use ($connectRegister, $master, $retrySecond) {
            Master::info($retrySecond .' 秒后重新连接');
            \React\EventLoop\Loop::get()->addTimer($retrySecond, function() use ($connectRegister, $master, $retrySecond) {
                $connectRegister($master, $retrySecond);
            });
        });

    },function($e) use ($connectRegister, $master, $retrySecond) {
        Master::info($e);
        Master::info($retrySecond .' 秒后重新连接');
        \React\EventLoop\Loop::get()->addTimer($retrySecond, function() use ($connectRegister, $master, $retrySecond) {
            $connectRegister($master, $retrySecond);
        });
    });
};

$connectRegister($master, 3);

$socket = new React\Socket\SocketServer(getParam('--master-address'));

$socket->on('connection', function (React\Socket\ConnectionInterface $connection) use ($master) {
    $master->emit('worker_open', [$connection]);
    $ndjson = new \Clue\React\NDJson\Decoder($connection, true);
    $ndjson->on('data', function ($data) use ($connection, $master) {
        $event = ($data['cmd'] ?? '') ?: ($data['event'] ?? '');

        if ($event) {
            $master->emit($event, [$connection, $data['data'] ?? []]);
        }
    });

    $ndjson->on('close', function () use ($connection, $master) {
        $master->emit('worker_close', [$connection]);
    });

});

echo "Master Listen At: ".getParam('--master-address')."\n";
