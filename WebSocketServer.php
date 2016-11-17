<?php
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
    const EVENT_WEBSOCKET_OPEN = 'ws_open';
    const EVENT_WEBSOCKET_CLOSE = 'ws_close';
    const EVENT_WEBSOCKET_OPEN_ERROR = 'ws_open_error';

    const EVENT_CLIENT_CONNECTED = 'ws_client_connected';
    const EVENT_CLIENT_ERROR = 'ws_client_error';
    const EVENT_CLIENT_DISCONNECTED = 'ws_client_disconnected';
    const EVENT_CLIENT_MESSAGE = 'ws_client_message';
    const EVENT_CLIENT_RUN_COMMAND = 'ws_client_run_command';
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
        /**
         * There u can parse message, check client rights, etc...
         *
         * Example:
         * Client sends json message like {"action":"chat", "text":"test message"}
         * if ($json = json_decode($msg)) { return $json['action'] }
         * If runMessageCommands == true && class method commandChat implemented,
         * Than will run $this->commandChat($client, $msg); $msg - source message string, $client - ConnectionInterface
         */
        return null;
    }
}