<?php
/**
 * @link https://github.com/consik/yii2-websocket
 * @category yii2-extension
 * @package consik\yii2websocket
 * 
 * @author Sergey Poltaranin <consigliere.kz@gmail.com>
 * @copyright Copyright (c) 2016
 */

namespace consik\yii2websocket;

use consik\yii2websocket\events\ExceptionEvent;
use consik\yii2websocket\events\WSClientCommandEvent;
use consik\yii2websocket\events\WSClientErrorEvent;
use consik\yii2websocket\events\WSClientEvent;
use consik\yii2websocket\events\WSClientMessageEvent;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\MessageComponentInterface;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use Symfony\Component\Console\Event\ConsoleEvent;
use Symfony\Component\Console\Event\ConsoleExceptionEvent;
use yii\base\Component;

class WebSocketServer extends Component implements MessageComponentInterface
{
    /**
     * @event yii\base\Event Triggered when binding is successfully completed 
     */
    const EVENT_WEBSOCKET_OPEN = 'ws_open';
    /**
     * @event yii\base\Event Triggered when socket listening is closed 
     */
    const EVENT_WEBSOCKET_CLOSE = 'ws_close';
    /**
     * @event ExceptionEvent Triggered when throwed Exception on binding socket
     */
    const EVENT_WEBSOCKET_OPEN_ERROR = 'ws_open_error';

    /**
     * @event WSClientEvent Triggered when client connected to the server
     */
    const EVENT_CLIENT_CONNECTED = 'ws_client_connected';
    /**
     * @event WSClientErrorEvent Triggered when an error occurs on a Connection
     */
    const EVENT_CLIENT_ERROR = 'ws_client_error';
    /**
     * @event WSClientEvent Triggered when client close connection with server
     */
    const EVENT_CLIENT_DISCONNECTED = 'ws_client_disconnected';
    /**
     * @event WSClientMessageEvent Triggered when message recieved from client
     */
    const EVENT_CLIENT_MESSAGE = 'ws_client_message';
    /**
     * @event WSClientCommandEvent Triggered when controller starts user's command
     */
    const EVENT_CLIENT_RUN_COMMAND = 'ws_client_run_command';
    /**
     * @event WSClientCommandEvent Triggered when controller finished user's command
     */
    const EVENT_CLIENT_END_COMMAND = 'ws_client_end_command';


    /**
     * @var int $port
     */
    public $port = 8080;

    /**
     * @var bool $closeConnectionOnError
     */
    protected $closeConnectionOnError = true;

    /**
     * @var bool $runMessageCommands
     */
    protected $runClientCommands = true;

    /**
     * @var IoServer|null $server
     */
    protected $server = null;

    /**
     * @var null|\SplObjectStorage $clients
     */
    protected $clients = null;

    /**
     * @return bool
     * 
     * @event yii\base\Event EVENT_WEBSOCKET_OPEN
     * @event ExceptionEvent EVENT_WEBSOCKET_OPEN_ERROR
     */
    public function start()
    {
        try {
            $this->server = IoServer::factory(
                new HttpServer(
                    new WsServer(
                        $this
                    )
                ),
                $this->port
            );
            $this->trigger(self::EVENT_WEBSOCKET_OPEN);
            $this->clients = new \SplObjectStorage();
            $this->server->run();

            return true;

        } catch (\Exception $e) {
            $errorEvent = new ExceptionEvent([
                'exception' => $e
            ]);
            $this->trigger(self::EVENT_WEBSOCKET_OPEN_ERROR, $errorEvent);
            return false;
        }
    }

    /**
     * @return void
     *
     * @event yii\base\Event EVENT_WEBSOCKET_CLOSE
     */
    public function stop()
    {
        $this->server->socket->shutdown();
        $this->trigger(self::EVENT_WEBSOCKET_CLOSE);
    }

    /**
     * @param ConnectionInterface $conn
     *
     * @event WSClientEvent EVENT_CLIENT_CONNECTED
     */
    function onOpen(ConnectionInterface $conn)
    {
        $this->trigger(self::EVENT_CLIENT_CONNECTED, new WSClientEvent([
            'client' => $conn
        ]));

        $this->clients->attach($conn);
    }

    /**
     * @param ConnectionInterface $conn
     *
     * @event WSClientEvent EVENT_CLIENT_DISCONNECTED
     */
    function onClose(ConnectionInterface $conn)
    {
        $this->trigger(self::EVENT_CLIENT_DISCONNECTED, new WSClientEvent([
            'client' => $conn
        ]));

        $this->clients->detach($conn);
    }

    /**
     * @param ConnectionInterface $conn
     * @param \Exception $e
     *
     * @event WSClientErrorEvent EVENT_CLIENT_ERROR
     */
    function onError(ConnectionInterface $conn, \Exception $e)
    {
        $this->trigger(self::EVENT_CLIENT_ERROR, new WSClientErrorEvent([
            'client' => $conn,
            'exception' => $e
        ]));

        if ($this->closeConnectionOnError) {
            $conn->close();
        }
    }

    /**
     * @param ConnectionInterface $from
     * @param string $msg
     *
     * @event WSClientMessageEvent EVENT_CLIENT_MESSAGE
     * @event WSClientCommandEvent EVENT_CLIENT_RUN_COMMAND
     * @event WSClientCommandEvent EVENT_CLIENT_END_COMMAND
     */
    function onMessage(ConnectionInterface $from, $msg)
    {
        $this->trigger(self::EVENT_CLIENT_MESSAGE, new WSClientMessageEvent([
            'client' => $from,
            'message' => $msg
        ]));

        if ($this->runClientCommands) {
            $command = $this->getCommand($from, $msg);

            if ($command && method_exists($this, 'command' . ucfirst($command))) {
                $this->trigger(self::EVENT_CLIENT_RUN_COMMAND, new WSClientCommandEvent([
                    'client' => $from,
                    'command' => $command
                ]));

                $result = call_user_func([$this, 'command' . ucfirst($command)], $from, $msg);

                $this->trigger(self::EVENT_CLIENT_END_COMMAND, new WSClientCommandEvent([
                    'client' => $from,
                    'command' => $command,
                    'result' => $result
                ]));
            }
        }
    }

    /**
     * @param ConnectionInterface $from
     * @param $msg
     * @return null|string - _NAME_ of command that implemented in class method command_NAME_()
     */
    protected function getCommand(ConnectionInterface $from, $msg)
    {
        return null;
    }
}
