<?php
namespace consik\yii2websocket\events;

class WSClientErrorEvent extends WSClientEvent
{
    /**
     * @var \Exception $exception
     */
    public $exception;
}