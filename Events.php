<?php


class Events
{

    public static function onWorkerStart($worker)
    {
       echo "WorkerStart\n";
    }


    public static function onOpen($client_id, $data)
    {
        Worker::info($client_id);
        Worker::info(json_encode($data));

    }


    public static function onMessage($client_id, $message)
    {
        Worker::info($client_id.'-'.$message);
    }


    public static function onClose($client_id)
    {
        Worker::info($client_id);
    }

}