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

    // ImClient::sendMessageByClientId($conn->client_id, json_encode([
    //     'event_type' => 'bind',
    //     'data' => [
    //         // 'client_id' => $conn->client_id.'-'. $conn->_id,
    //         'client_id' => $conn->_id,
    //         'msg' => 'bind success:'.$conn->client_id. '-'. $conn->_id
    //     ]
    // ]));
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




$im->on('echo', function(WebSocketConnection $conn, $data){
    $conn->send(json_encode([
        'event_type' => 'echo',
        'data' => [
            'client_id' => $conn->client_id,
            'msg' => '【系统消息】已接收到消息-'.$data['value'] ?? ''
        ]
    ]));
});

$im->on('getClientId', function(WebSocketConnection $conn, $data){
    $conn->send(json_encode([
        'event_type' => 'echo',
        'data' => [
            'client_id' => $conn->client_id,
            'msg' => '【系统消息】您的Client ID 为'."【{$conn->client_id}】"
        ]
    ]));
});
$im->on('getOnlineClientIds', function(WebSocketConnection $conn, $data){
    $conn->send(json_encode([
        'event_type' => 'onOnlineClientIds',
        'data' => [
            'client_id' => $conn->client_id,
            'msg' => '【系统消息】',
            'data' => ImClient::getClientIds()
        ]
    ]));
});

$im->on('broadcast', function(WebSocketConnection $conn, $data){
    
    $type = $data['type'] ?? 'all';
    $excludeClientIds = [];

    if ($type == 'other') {
        $excludeClientIds[] = $conn->client_id;
    }
    ImClient::broadcast(json_encode([
        'event_type' => 'broadcast',
        'data' => [
            'client_id' => $conn->client_id,
            'msg' => '【广播消息】已接收到消息-'.$data['value'] ?? ''
        ]
    ]), $excludeClientIds);

    if ($type == 'other') {
       ImClient::sendMessageByClientId($conn->client_id, 
            json_encode([
                'event_type' => 'broadcast',
                'data' => [
                    'client_id' => $conn->client_id,
                    'msg' => '【广播消息】广播成功'
                ]
            ])
        );
    }
});

$im->on('sendMessage', function(WebSocketConnection $conn, $data){
    $data['group_id'] = $data['group_id'] ?? 1;
    ImClient::joinGroupByClientId($data['group_id'], $conn->client_id);
    $data['client_id'] = $conn->client_id;
    ImClient::sendMessageToGroupByClientId($data['group_id'], json_encode([
        'event_type' => 'sendMessage',
        'data' => $data
    ]));
});

$im->on('beforeClose', function(WebSocketConnection $conn){
    Imclient::sendMessageToGroupByOnlyClientId($conn->client_id, [
        'event_type' => 'sendMessage',
        'data' => [
            'msg' => '【'.$conn->client_id.'】'.'离开了聊天室',
            'id' => 0,
            'from' => [
                'id' => 0,
                'name' => '系统消息',
                'avatar' => [
                    'url' => 'https://picsum.photos/300'
                ],
            ],
            'to' => [
                'id' => 0,
                'name' => '系统消息',
                'avatar' => [
                    'url' => 'https://picsum.photos/300'
                ],
            ],
            'data' => [
                'content' => '【'.$conn->client_id.'】'.'离开了聊天室'
            ],
        ]
        
    ]);
});

$im->on('sendMessageByClientId', function(WebSocketConnection $from, $data){
    $client_id = $data['client_id'] ?? '';
    $msg = $data['value'] ?? '';
    if (!$client_id){
        ImClient::sendMessageByClientId($from->client_id, json_encode([
            'event_type' => 'sendMessageByClientId',
            'data' => [
                'client_id' => $from->client_id,
                'msg' => 'client_id is empty'
            ]
        ]));
        return;
    }

    if (ImClient::isExistByClientId($client_id) === false) {
        ImClient::sendMessageByClientId($from->client_id, json_encode([
            'event_type' => 'sendMessageByClientId',
            'data' => [
                'client_id' => $from->client_id,
                'msg' => 'client_id is not exist'
            ]
        ]));
        return;
    }

    if (!$msg){
        ImClient::sendMessageByClientId($from->client_id, json_encode([
            'event_type' => 'sendMessageByClientId',
            'data' => [
                'client_id' => $from->client_id,
                'msg' => 'msg is empty'
            ]
        ]));
        return;
    }
    ImClient::sendMessageByClientId($from->client_id, json_encode([
        'event_type' => 'sendMessageByClientId',
        'data' => [
            'client_id' => $from->client_id,
            'msg' => '【系统消息】'.'【'.$from->client_id.'】'.'信息发送成功'
        ]
    ]));
    ImClient::sendMessageByClientId($client_id, json_encode([
        'event_type' => 'sendMessageByClientId',
        'data' => [
            'client_id' => $from->client_id,
            'msg' => '【from】【'.$client_id.'】'.$msg
        ]
    ]));
});

$im->on('joinGroupByClientId', function(WebSocketConnection $from, $data){
    $group_id = $data['group_id'] ?? '';
    $client_id = $data['client_id'] ?? '';
    if (!$group_id){
        ImClient::sendMessageByClientId($client_id, json_encode([
            'event_type' => 'joinGroupByClientId',
            'data' => [
                'client_id' => $client_id,
                'msg' => '房间号不能是空'
            ]
        ]));
        return;
    }
    $state = ImClient::joinGroupByClientId($group_id, $client_id);

    $msg = "加入房间-$group_id 成功";
    if (!$state) {
        $msg = "加入房间-$group_id 失败";
    } elseif ($state === 1) {
        $msg = "已经加入房间-$group_id";
    } 

    $groupCount = ImClient::getGroupClientCount($group_id);

    // 加入房间后发送一条消息（不需要绑定）
    ImClient::sendMessageToGroupByClientId($group_id, json_encode([
        'event_type' => 'joinGroupByClientId',
        'data' => [
            'client_id' => $client_id,
            'msg' => "【 房间-$group_id -人数-$groupCount 】".'【'.$client_id.'】'.$msg
        ]
    ]));
    // 你已经加入的房间为
    $groupIds = ImClient::getGroupIdsByClientId($client_id);

    ImClient::sendMessageByClientId($client_id, json_encode([
        'event_type' => 'joinGroupByClientId',
        'data' => [
            'client_id' => $client_id,
            'msg' => "【 房间-$group_id -人数-$groupCount 】".'【'.$client_id.'】加入的所有房间ID为'.implode(',', $groupIds)
        ]
    ]));

});

$im->on('leaveGroupByClientId', function(WebSocketConnection $from, $data){
    $group_id = $data['group_id'] ?? '';
    $client_id = $data['client_id'] ?? '';
    if (!$group_id){
        ImClient::sendMessageByClientId($client_id, json_encode([
            'event_type' => 'leaveGroupByClientId',
            'data' => [
                'client_id' => $client_id,
                'msg' => '房间号不能是空'
            ]
        ]));
        return;
    }
    $state = ImClient::leaveGroupByClientId($group_id, $client_id);

    $msg = "离开房间-$group_id 成功";
    if (!$state) {
        $msg = "离开房间-$group_id 失败";
    } elseif ($state === 1) {
        $msg = "已经离开房间-$group_id";
    } 
    $groupCount = ImClient::getGroupClientCount($group_id);

    // 离开房间后发送一条消息（不需要绑定）
    ImClient::sendMessageToGroupByClientId($group_id, json_encode([
        'event_type' => 'leaveGroupByClientId',
        'data' => [
            'client_id' => $client_id,
            'msg' => "【 房间-$group_id -人数-$groupCount 】".'【'.$client_id.'】'.$msg
        ]
    ]));

    // 你已经加入的房间为
    $groupIds = ImClient::getGroupIdsByClientId($client_id);
    ImClient::sendMessageByClientId($client_id, json_encode([
        'event_type' => 'leaveGroupByClientId',
        'data' => [
            'client_id' => $client_id,
            'msg' => "【 房间-$group_id -人数-$groupCount 】".'【'.$client_id.'】'.$msg.'您加入的所有房间ID为'.implode(',', $groupIds)
        ]
    ]));
});

$im->on('sendMessageToGroupByClientId', function(WebSocketConnection $from, $data){
    $group_id = $data['group_id'] ?? '';
    $client_id = $data['client_id'] ?? '';
    $msg = $data['value'] ?? '';
    if (!$group_id){
        $from->send(json_encode([
            'event_type' => 'sendMessageToGroupByClientId',
            'data' => [
                'client_id' => $client_id,
                'msg' => '房间号 不能为空'
            ]
        ]));
        return;
    }
    $groupCount = ImClient::getGroupClientCount($group_id);

    if (!ImClient::isInGroupByClientId($group_id, $client_id)) {
        ImClient::sendMessageByClientId($client_id, json_encode([
            'event_type' => 'sendMessageToGroupByClientId',
            'data' => [
                'client_id' => $client_id,
                'msg' => "【 房间-$group_id -人数-$groupCount 】".'你不在 '.$group_id.' 中'
            ]
        ]));
        return;
    }

    $type = $data['type'] ?? 'all';
    $excludeClientIds = [];
    if ($type === 'other') {
        $excludeClientIds = [
            $client_id
        ];
    }
    $groupCount = ImClient::getGroupClientCount($group_id);


    $state = ImClient::sendMessageToGroupByClientId($group_id, json_encode([
        'event_type' => 'sendMessageToGroupByClientId',
        'data' => [
            'client_id' => $client_id,
            'msg' => "【 房间-$group_id -人数-$groupCount 】".'【'.$client_id.'】'.$msg
        ]
    ]), $excludeClientIds);

    if ($type === 'other') {
        ImClient::sendMessageByClientId($client_id, json_encode([
            'event_type' => 'sendMessageToGroupByClientId',
            'data' => [
                'client_id' => $client_id,
                'msg' => "【 房间-$group_id -人数-$groupCount 】给其他人发送成功"
            ]
        ]));
    }

    $msg = "send $group_id success";
    if (!$state) {
        $msg = "发送 $group_id 失败";
        ImClient::sendMessageByClientId($client_id, json_encode([
            'event_type' => 'sendMessageToGroupByClientId',
            'data' => [
                'client_id' => $client_id,
                'msg' => "【 房间-$group_id -人数-$groupCount 】".'【'.$client_id.'】'.$msg
            ]
        ]));
    } elseif ($state === 1) {
        $msg = "没有人在 $group_id";
        ImClient::sendMessageByClientId($client_id, json_encode([
            'event_type' => 'sendMessageToGroupByClientId',
            'data' => [
                'client_id' => $client_id,
                'msg' =>"【 房间-$group_id -人数-$groupCount 】".'【'.$client_id.'】'. $msg
            ]
        ]));
    }

   
});

$im->on('close', function($code, $conn, $reason){

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


        $queryParams = $request->getQueryParams();

        $type = $queryParams['type'] ?? '';
        $msg = $queryParams['msg'] ?? '';
        $msg .= '-'. $type; 

        // 绑定用户ID和client_id(可以一对多)
        if ($type == 'bind') {

            $id = $queryParams['id'] ?? '';
            $client_id = $queryParams['client_id'] ?? '';
            ImClient::bind($id, $client_id);
            ImClient::sendMessage($id, $msg);

        } 
        // 解绑用户ID下的所有client_id（解绑后通过用户ID发送消息不能发出）
        elseif ($type == 'unBind') {

            $id = $queryParams['id'] ?? '';
            // 解绑前，给客户端发送一条消息（解绑后解发送不了了）
            ImClient::sendMessage($id, $msg);
            ImClient::unbind($id);

        }
        // 解绑用户ID下的某个client_id
        elseif ($type == 'unBindByClientId') {

            $client_id = $queryParams['client_id'] ?? '';
            ImClient::unBindByClientId($client_id);
            // 解绑后，给客户端发送一条消息
            ImClient::sendMessageByClientId($client_id, $msg);

        } 
        // 加入房间（需先绑定-常用于有用户体系的场景）
        elseif ($type == 'joinGroup') {

            $group_id = $queryParams['group_id'] ?? '';
            $id = $queryParams['id'] ?? '';
            ImClient::joinGroup($group_id, $id);
            // 加入群组后发送一条消息（需要绑定-bind）
            ImClient::sendMessageToGroup($group_id, $msg);

        }
        // 加入房间（不需要绑定-常用于没有用户体系的场景）
        elseif ($type == 'joinGroupByClientId') {

            $group_id = $queryParams['group_id'] ?? '';
            $client_id = $queryParams['client_id'] ?? '';
            ImClient::joinGroupByClientId($group_id, $client_id);
            // 加入房间后发送一条消息（不需要绑定）
            ImClient::sendMessageToGroupByClientId($group_id, $msg);

        } 
        // 离开房间（需要绑定-bind）
        elseif ($type == 'leaveGroup') {

            $group_id = $queryParams['group_id'] ?? '';
            $id = $queryParams['id'] ?? '';
            // 在离开群组之前（需要绑定-bind），先发送一条消息
            ImClient::sendMessageToGroup($group_id, $msg);

            // 需要事先加入组才能离开否则没有效果
            ImClient::leaveGroup($group_id, $id);
            // 在离开群组之后，再发送一条消息（离开的人收不到）
            ImClient::sendMessageToGroup($group_id, $msg);

        } 
        // 离开房间（不需要绑定，仅离开房间，不影响ID和client_id 的绑定关系）
        elseif ($type == 'leaveGroupByClientId') {

            $group_id = $queryParams['group_id'] ?? '';
            $client_id = $queryParams['client_id'] ?? '';
            // 在离开房间之前(不需要绑定)，先发送一条消息
            ImClient::sendMessageToGroupByClientId($group_id, $msg);
            ImClient::leaveGroupByClientId($group_id, $client_id);  
            // 在离开房间之后（不需要绑定），再发送一条消息（离开的人收不到）
            ImClient::sendMessageToGroupByClientId($group_id, $msg);

        } 
        // 离开所有房间（需要绑定-bind）
        elseif ($type == 'leaveAllGroup') {

            $id = $queryParams['id'] ?? '';
            // 在离开所有房间之前，先发送一条消息
            ImClient::sendMessageToGroupByOnlyId($id, $msg);
            ImClient::leaveAllGroup($id);
            // 在离开所有房间之后，再发送一条消息（离开的人收不到）
            ImClient::sendMessageToGroupByOnlyId($id, $msg);

        } 
        // 离开所有房间（不需要绑定，仅离开房间，不影响ID和client_id 的绑定关系）
        elseif ($type == 'leaveAllGroupByClientId') {

            $client_id = $queryParams['client_id'] ?? '';
            // 在离开所有群组之前，先发送一条消息
            ImClient::sendMessageToGroupByOnlyClientId($client_id, $msg);
            ImClient::leaveAllGroupByClientId($client_id);
            // 在离开所有群组之后，再发送一条消息（离开的人收不到）
            ImClient::sendMessageToGroupByOnlyClientId($client_id, $msg);

        } 
        // 发送消息 （需要绑定-bind）
        elseif ($type == 'sendMessage') {

            $id = $queryParams['id'] ?? '';
            // 通过ID发送消息（需要先绑定-bind）
            ImClient::sendMessage($id, $msg);

        } 
        // 发送消息 （不需要绑定）
        elseif ($type == 'sendMessageByClientId') {

            $client_id = $queryParams['client_id'] ?? '';
            // 通过client_id发送消息（不需要绑定）
            ImClient::sendMessageByClientId($client_id, $msg);

        } 
        // 给房间发送消息（需要绑定-bind）
        elseif ($type == 'sendMessageToGroup') {

            $group_id = $queryParams['group_id'] ?? '';
            // 给房间发送消息(需要先绑定),第三个参数是排除的ID
            ImClient::sendMessageToGroup($group_id, $msg, []);

        } 
        // 给房间发送消息（不需要绑定）
        elseif ($type == 'sendMessageToGroupByClientId') {
            $group_id = $queryParams['group_id'] ?? '';
            // 给房间发送消息（不需要绑定）,第三个参数是排除的client_id
            ImClient::sendMessageToGroupByClientId($group_id, $msg);

        }

        $id = $queryParams['id'] ?? '';
        $group_id = $queryParams['group_id'] ?? '';
        $client_id = $queryParams['client_id'] ?? '';
        $msg = $queryParams['msg'] ?? '';

        $str = <<<EOF
        <a href="/test?type=bind&id=$id&client_id=$client_id&group_id=$group_id&msg=$msg">bind</a><br>
        <a href="/test?type=unBind&id=$id&client_id=$client_id&group_id=$group_id&msg=$msg">unBind(依赖【bind】)</a><br>
        <a href="/test?type=unBindByClientId&id=$id&client_id=$client_id&group_id=$group_id&msg=$msg">unBindByClientId</a><br>
        <a href="/test?type=joinGroup&id=$id&client_id=$client_id&group_id=$group_id&msg=$msg">joinGroup-(依赖【bind】)</a><br>
        <a href="/test?type=joinGroupByClientId&id=$id&client_id=$client_id&group_id=$group_id&msg=$msg">joinGroupByClientId</a><br>
        <a href="/test?type=leaveGroup&id=$id&client_id=$client_id&group_id=$group_id&msg=$msg">leaveGroup-(依赖【bind】【joinGroup】)</a><br>
        <a href="/test?type=leaveGroupByClientId&id=$id&client_id=$client_id&group_id=$group_id&msg=$msg">leaveGroupByClientId</a><br>
        <a href="/test?type=leaveAllGroup&id=$id&client_id=$client_id&group_id=$group_id&msg=$msg">leaveAllGroup-(依赖【bind】【joinGroup】)</a><br>
        <a href="/test?type=leaveAllGroupByClientId&id=$id&client_id=$client_id&group_id=$group_id&msg=$msg">leaveAllGroupByClientId</a><br>
        <a href="/test?type=sendMessage&id=$id&client_id=$client_id&group_id=$group_id&msg=$msg">sendMessage-(依赖【bind】)</a><br>
        <a href="/test?type=sendMessageByClientId&id=$id&client_id=$client_id&group_id=$group_id&msg=$msg">sendMessageByClientId</a><br>
        <a href="/test?type=sendMessageToGroup&id=$id&client_id=$client_id&group_id=$group_id&msg=$msg">sendMessageToGroup-(依赖【bind】【joinGroup】)</a><br>
        <a href="/test?type=sendMessageToGroupByClientId&id=$id&client_id=$client_id&group_id=$group_id&msg=$msg">sendMessageToGroupByClientId</a><br>
        EOF;

        return React\Http\Message\Response::html(
            $str
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
    echo sprintf('%s-%s-%s-%s-%s', Helper::formatTime(time() - $startTime),'after_memory',Helper::formatMemory(memory_get_usage(true)), $numBytes, Im::$clients->count().'-'.count(Im::$client_id_to_client))."\n";
    echo sprintf("%s-%s", 'client:count', ImClient::getClientCount())."\n";
    echo sprintf("%s-%s", 'bind:id:count', ImClient::getBindIdCount())."\n";
    echo sprintf("%s-%s", 'bind:client_id:count', ImClient::getBindClientCount())."\n";
    echo sprintf("%s-%s", 'group:count', ImClient::getGroupCount())."\n";
    echo "\n";
});
