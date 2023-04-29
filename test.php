<?php

require './vendor/autoload.php';

use Wpjscc\Websocket\WebSocketMiddleware;
use Wpjscc\Websocket\Chat;
use Wpjscc\Websocket\Im;
use Wpjscc\Websocket\ImClient;
use Wpjscc\Websocket\Helper;

$chat = null;

$server = new \React\Http\HttpServer(
    new WebSocketMiddleware([
        '/websocket'
    ], new Im),
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

$socket = new \React\Socket\SocketServer('0.0.0.0:8088');
$server->listen($socket);

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