<?php

namespace Wpjscc\Websocket;

class ImClient
{

    // 绑定client
    public static function bind($id, $client_id)
    {
        $client = Im::$client_id_to_client[$client_id] ?? null;

        if ($client) {
            // 避免被重复绑定（一个ID可以对应多个client_id, 但一个client_id 只能对应一个ID）
            if(!isset(Im::$client_id_to_id[$client_id])){
                Im::$id_to_client_ids[$id][$client_id] = $client_id;
                Im::$client_id_to_id[$client_id] = $id;
            }
           
        }

    }

    // 通过ID解绑所有client
    public static function unBind($id)
    {
        $client_ids = Im::$id_to_client_ids[$id] ?? [];
        foreach ($client_ids as $client_id) {
            static::unBindByIdAndClientId($id, $client_id);
        }

    }

    // 通过clientid解绑
    public static function unBindByClientId($client_id)
    {
        $id = Im::$client_id_to_id[$client_id] ?? 0;
        if ($id) {
            static::unBindByIdAndClientId($id, $client_id);
        }
    }
    
    // 通过ID和clientId解绑
    public static function unBindByIdAndClientId($id, $client_id)
    {
        if (isset(Im::$id_to_client_ids[$id][$client_id])){
            
            // 这个client离开所有房间（取消注释后 不能在群里收到信息。注释掉后通过 sendMessageToGroupByClientId 发送消息，用户还能收到，通过 sendMessageToGroup 发送消息用户收不到）
            // static::leaveAllGroupByClientId($client_id);

            // 清除对应关系
            unset(Im::$id_to_client_ids[$id][$client_id]);
            unset(Im::$client_id_to_id[$client_id]);
            if (count(Im::$id_to_client_ids[$id]) == 0) {
                unset(Im::$id_to_client_ids[$id]);
            }
        }

    }



    public static function isOnline($id)
    {
        return isset(Im::$id_to_client_ids[$id]);
    }

    public static function isOnlineByClientId($client_id)
    {
        return isset(Im::$client_id_to_id[$client_id]);
    }

    // 获取在线人数
    public static function onlineCount()
    {
        return count(Im::$client_id_to_client);
    }

    // 加入房间
    public static function joinGroup($group_id, $id)
    {
        $client_ids = Im::$id_to_client_ids[$id] ?? [];
        foreach ($client_ids as $client_id) {
            $client = Im::$client_id_to_client[$client_id] ?? null;
            if ($client) {
                Im::joinGroup($group_id, $client);
            }
        }
    }

    // 通过clientId加入房间
    public static function joinGroupByClientId($group_id, $client_id)
    {
        $client = Im::$client_id_to_client[$client_id] ?? null;
        if ($client) {
            return Im::joinGroup($group_id, $client);
        }
        return false;
    }


    // 离开房间
    public static function leaveGroup($group_id, $id)
    {
        $client_ids = Im::$id_to_client_ids[$id] ?? [];
        foreach ($client_ids as $client_id) {
            $client = Im::$client_id_to_client[$client_id] ?? null;
            if ($client) {
                Im::leaveGroup($group_id, $client);
            }
        }
    }

    // 通过clientid离开房间
    public static function leaveGroupByClientId($group_id, $cliend_id)
    {
        $client = Im::$client_id_to_client[$cliend_id] ?? null;
        if ($client) {
            return Im::leaveGroup($group_id, $client);
        }
        return false;
    }


    // 离开所有房间
    public static function leaveAllGroup($id)
    {
        $client_ids = Im::$id_to_client_ids[$id] ?? [];
        foreach ($client_ids as $client_id) {
            $client = Im::$client_id_to_client[$client_id] ?? null;
            if ($client) {
                Im::leaveAllGroup($client);
            }
        }
    }



    // 通过clientid离开所有房间
    public static function leaveAllGroupByClientId($cliend_id)
    {
        $client = Im::$client_id_to_client[$cliend_id] ?? null;
        if ($client) {
            Im::leaveAllGroup($client);
        }

    }


    public static function sendMessage($id, $message)
    {
        $client_ids = Im::$id_to_client_ids[$id] ?? [];
        foreach ($client_ids as $client_id) {
            Im::sendMessageToClient($client_id, $message);
        }
    }

    public static function sendMessageByClientId($client_id, $message)
    {
        Im::sendMessageToClient($client_id, $message);
    }

    // 给指定房间下用户ID发送消息
    public static function sendMessageToGroup($group_id, $message, $excludeIds = [])
    {
        $group = Im::$groups[$group_id] ?? null;
        if ($group) {
            $excludeClients = [];
            if ($excludeIds) {
                foreach ($excludeIds as $excludeId) {
                    $clientIds = Im::$id_to_client_ids[$excludeId] ?? [];
                    foreach($clientIds as $clientId) {
                        $excludeClient = Im::$client_id_to_client[$clientId] ?? null;
                        if ($excludeClient){
                            $excludeClients[] = $excludeClient;
                        }
                    }
                }
            }
            Im::sendMessageToGroup($group_id, $message, $excludeClients);
        }
    }

    // 给指定房间下clientid发送消息
    public static function sendMessageToGroupByClientId($group_id, $message, $excludeClientIds = [])
    {
        $group = Im::$groups[$group_id] ?? null;
        if ($group) {
            $excludeClients = [];
            if ($excludeClientIds) {
                foreach ($excludeClientIds as $excludeClientId) {
                    $excludeClient = Im::$client_id_to_client[$excludeClientId] ?? null;
                    if ($excludeClient){
                        $excludeClients[] = $excludeClient;
                    }
                }
            }
            return Im::sendMessageToGroup($group_id, $message, $excludeClients);
        }
        return false;
    }

    public static function isInGroup($group_id, $id)
    {
        $client_ids = Im::$id_to_client_ids[$id] ?? [];
        foreach ($client_ids as $client_id) {
            $group_ids = Im::$client_id_to_group_ids[$client_id] ?? [];
            if (in_array($group_id, $group_ids)) {
                return true;
            }
        }
        return false;
    }

    public static function isInGroupByClientId($group_id, $client_id)
    {
        $group_ids = Im::$client_id_to_group_ids[$client_id] ?? [];
        if (in_array($group_id, $group_ids)) {
            return true;
        }
        return false;
    }

    // 给用户ID下的所有房间发送消息
    public static function sendMessageToGroupByOnlyId($id, $message, $excludeIds = [])
    {
        $cliend_ids = Im::$id_to_client_ids[$id] ?? [];
        foreach($cliend_ids as $client_id) {
            $group_ids = Im::$client_id_to_group_ids[$client_id] ?? [];
            foreach ($group_ids as $group_id) {
                Im::sendMessageToGroup($group_id, $message, $excludeIds);
            }
        }
    }

    // 给clientId下的所有房间发送消息
    public static function sendMessageToGroupByOnlyClientId($client_id, $message, $excludeCliientIds = [])
    {
        $group_ids = Im::$client_id_to_group_ids[$client_id] ?? [];

        foreach ($group_ids as $group_id) {
            if (is_array($message)) {
                $message['data']['group_id'] = $group_id;
                $message = json_encode($message);
            }
            Im::sendMessageToGroup($group_id, $message, $excludeCliientIds);
        }
    }

    public static function broadcast($message, $excludeClientIds = [])
    {
        foreach (Im::$clients as $client) {
            if (!in_array($client->client_id, $excludeClientIds)) {
                $client->send($message);
            }
        } 
    }

    // 获取房间的数量
    public static function getGroupCount()
    {
        return count(Im::$groups);
    }

    // 获取房间下的客户端数量
    public static function getGroupClientCount($group_id)
    {
        if (!isset(Im::$groups[$group_id])) {
            return 0;
        }

        return Im::$groups[$group_id]->count();
    }

    // 绑定ID的数量
    public static function getBindIdCount()
    {
        return count(Im::$id_to_client_ids);
    }

    // 绑定cliengid的数量
    public static function getBindClientCount()
    {
        return count(Im::$client_id_to_id);
    }

    // 客户端的数量
    public static function getClientCount()
    {
        return count(Im::$client_id_to_client);
    }

    // 获取所有在线的client id
    public static function getClientIds()
    {
        return array_keys(Im::$client_id_to_client);
    }


    public static function getGrouIdsById($id)
    {
        $cliend_ids = Im::$id_to_client_ids[$id] ?? [];
        $group_ids = [];
        foreach ($cliend_ids as $client_id) {
            $group_ids = array_unique(array_merge($group_ids, Im::$client_id_to_group_ids[$client_id] ?? []));
        }
        return $group_ids;
    }

    public static function getGroupIdsByClientId($client_id)
    {
        return Im::$client_id_to_group_ids[$client_id] ?? [];
    }

    // id 加入房间数量
    public static function getGroupCountById($id)
    {
        return count(static::getGrouIdsById($id));
    }

    // clientid 加入房间数量
    public static function getGroupCountByClientId($client_id)
    {
        return count(static::getGroupIdsByClientId($client_id));
    }


    public static function isExistByClientId($client_id)
    {
        return isset(Im::$client_id_to_client[$client_id]);
    }



}