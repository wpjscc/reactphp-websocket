<?php

namespace Wpjscc\Websocket;

use Ratchet\RFC6455\Messaging\Message;

class Im
{
    public static $clients;
    public static $client_id_to_client = [];

    public function __construct() 
    {
        static::$clients = new \SplObjectStorage;
    }
    
    public function __invoke(WebSocketConnection $conn)
    {
        $conn->on('open', function($conn) {
            $conn->send($conn->client_id);
            $this->onOpen($conn);
        });

        $conn->on('message', function (Message $message) use ($conn) {
            $this->onMessage($conn, $message);
        });
        $conn->on('close', function ($code, $conn, $reason) {
            $this->onClose($conn);
        });
    }

    public function onOpen(WebSocketConnection $conn)
    {
        static::$clients->attach($conn);
        static::$client_id_to_client[$conn->client_id] = $conn;
    }

    public function onMessage(WebSocketConnection $from, $msg)
    {
        // todo handle message
        echo $msg."\n";
        $from->send($msg);
    }

    public function onClose(WebSocketConnection $conn)
    {
        // 清除链接信息
        static::$clients->detach($conn);
        unset(static::$client_id_to_client[$conn->client_id]);

        // 清除分组对应关系，如果存在的话
        static::leaveAllGroup($conn);

        // 清除对应关系如果存在的话（用于有自定义的用户关系）
        $id = static::$client_id_to_id[$conn->client_id] ?? 0;
        if ($id) {
            // 清除client_id 和
            unset(static::$client_id_to_id[$conn->client_id]);
            unset(static::$id_to_client_ids[$id][$conn->client_id]);
            if (count(static::$id_to_client_ids[$id]) == 0) {
                unset(static::$id_to_client_ids[$id]);
            }
        }
    }



    // 分组相关
    public static $groups = [];
    public static $client_id_to_group_ids= [];

    // 用户ID和clientId的绑定关系（一对多）
    public static $id_to_client_ids = [];
    // clientId 和用户ID的对应关系（多对1）
    public static $client_id_to_id = [];

    public static function joinGroup($groupId, $client)
    {
        if (!isset(static::$groups[$groupId])) {
            static::$groups[$groupId] = new \SplObjectStorage;
        }
        
        // 避免重复加入
        if (static::$groups[$groupId]->contains($client)) {
            return;
        }

        static::$groups[$groupId]->attach($client);
        static::$client_id_to_group_ids[$client->client_id][$groupId] = $groupId;
    }

    public static function leaveGroup($groupId, $client)
    {
        if ($client && isset(static::$groups[$groupId])) {
            if (isset(static::$client_id_to_group_ids[$client->client_id][$groupId])) {

                static::$groups[$groupId]->detach($client);
                unset(static::$client_id_to_group_ids[$client->client_id][$groupId]);

                // 避免无效的占用空数据
                if (static::$groups[$groupId]->count() == 0) {
                    unset(static::$groups[$groupId]);
                }

                // 避免无效的占用空数据
                if (count(static::$client_id_to_group_ids[$client->client_id]) == 0) {
                    unset(static::$client_id_to_group_ids[$client->client_id]);
                }
            }
        }
    }

    public static function leaveAllGroup($client)
    {
        if ($client && isset(static::$client_id_to_group_ids[$client->client_id])) {
            foreach (static::$client_id_to_group_ids[$client->client_id] as $groupId) {
                static::leaveGroup($groupId, $client);
            }
        }
    }

    public static function sendMessageToGroup($groupId, $msg, $excludeClients = [])
    {
        if (isset(static::$groups[$groupId])) {
            foreach (static::$groups[$groupId] as $client) {
                if (in_array($client, $excludeClients)) {
                    continue;
                }
                $client->send($msg);
            }
        }
    }

    public static function sendMessageToClient($clientId, $msg)
    {
       $client = static::$client_id_to_client[$clientId] ?? null;

       if ($client) {
           $client->send($msg);
       }
    }


       

    
}