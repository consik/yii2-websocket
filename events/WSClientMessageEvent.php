<?php
namespace consik\yii2websocket\events;

class WSClientMessageEvent extends WSClientEvent
{
    /**
     * @var string $message
     */
    public $message;
}