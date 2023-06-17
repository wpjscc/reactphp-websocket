<?php

require './vendor/autoload.php';

use Wpjscc\Websocket\WebSocketMiddleware;
use Wpjscc\Websocket\Chat;
use Wpjscc\Websocket\Im;
use Wpjscc\Websocket\ImClient;
use Wpjscc\Websocket\Helper;
use Wpjscc\Websocket\WebSocketConnection;
use Wpjscc\MasterWorker\ConnectionManager;
use Wpjscc\MasterWorker\Master;


Master::instance()->run();

$im = new Im();

$im->on('open', function(WebSocketConnection $conn, $request){
    Master::instance()->emit('client_open', [$conn, [
        'headers' => $request->getHeaders(),
        'get' => $request->getQueryParams()
    ]]);

});

$im->on('message', function(WebSocketConnection $conn, $message) use ($im) {
    Master::instance()->emit('client_message', [$conn, $message->getPayload()]);

    try {
        $data = json_decode($message->getPayload(), true);
        if (isset($data['event_type']) && !in_array($data['event_type'], ['open', 'message', 'close'])) {
            $im->emit($data['event_type'], [$conn, $data['data'] ?? []]);
        }
    } catch (\Throwable $th) {
        //throw $th;
    }
});

$im->on('close', function(WebSocketConnection $conn){
    Master::instance()->emit('client_close', [$conn]);
    echo "client:count:". ConnectionManager::instance('client')->getConnectionCount()."\n";
});


$server = new \React\Http\HttpServer(
    new WebSocketMiddleware([
        '/websocket'
    ], $im),
    function (\Psr\Http\Message\ServerRequestInterface $request) {
       
        $path = $request->getUri()->getPath();

        if ($path == '/') {
            return \React\Http\Message\Response::html(file_get_contents('./index.html'));
        }

        return React\Http\Message\Response::html(
            'hwllo world'
        );
    }
);

function getParam($key, $default = null) {
    foreach ($GLOBALS['argv'] as $arg) {
        if (strpos($arg, $key) !==false) {
            return explode('=', $arg)[1];
        }
    }
    return $default;
}

$socket = new \React\Socket\SocketServer(getParam('--server-address')?:'0.0.0.0:8088');
$server->listen($socket);

echo "Listen At: ".getParam('--server-address')?:'0.0.0.0:8088';

$startTime = time();

\React\EventLoop\Loop::get()->addPeriodicTimer(3, function() use ($startTime){
    $numBytes = gc_mem_caches();
    echo sprintf('%s', implode(',', ConnectionManager::instance('client')->get_Ids()))."\n";
    echo sprintf('group_ids-%s', implode(',', ConnectionManager::instance('client')->getGroupIds()))."\n";
    // echo sprintf('connection_id_to_group_ids-%s', json_encode(ConnectionManager::instance('client')->connection_id_to_group_ids))."\n";
    // echo sprintf('id_to_connection_ids-%s', json_encode(ConnectionManager::instance('client')->id_to_connection_ids))."\n";
    // echo sprintf('id_to_connection_ids-%s', json_encode(ConnectionManager::instance('client')->id_to_connection_ids))."\n";
    // echo sprintf('connection_id_to_id-%s', json_encode(ConnectionManager::instance('client')->connection_id_to_id))."\n";
    // echo sprintf('instance-%s', json_encode(array_keys(ConnectionManager::instance('client')::$instance)))."\n";
    echo sprintf('%s', ConnectionManager::instance('client')->getConnectionCount())."\n";
    echo sprintf('%s-%s-%s-%s', Helper::formatTime(time() - $startTime),'after_memory',Helper::formatMemory(memory_get_usage(true)), $numBytes)."\n";
   
    echo "\n";
});
