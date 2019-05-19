<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Concerns;

use HZEX\TpSwoole\WebSocket\HandlerContract;
use Swoole\Http\Request;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server as WebSocketServer;
use think\Container;

/**
 * Trait InteractsWithWebsocket
 * @package HZEX\TpSwoole\Concerns
 * @property Container $container
 * @property Container $events
 */
trait InteractsWithWebsocket
{
    /**
     * @var boolean
     */
    protected $isServerWebsocket = false;

    /**
     * @var HandlerContract
     */
    protected $websocketHandler;

    /**
     * Websocket server events.
     *
     * @var array
     */
    protected $wsEvents = ['HandShake', 'Open', 'Message'];

    /**
     * Prepare settings if websocket is enabled.
     */
    protected function prepareWebsocket()
    {
        $config      = $this->container->make('config');
        $isWebsocket = $config->get('swoole.websocket.enabled');

        if ($isWebsocket) {
            $this->events = array_merge($this->events ?? [], $this->wsEvents);
            $this->isServerWebsocket = true;
        }
    }


    /**
     * 连接建立回调（WebSocket）
     * @param WebSocketServer $server
     * @param Request         $request
     */
    protected function onOpen(WebSocketServer $server, Request $request)
    {
    }

    /**
     * 消息到达回调（WebSocket）
     * @param WebSocketServer $server
     * @param Frame           $frame
     */
    protected function onMessage(WebSocketServer $server, Frame $frame)
    {
    }

    /**
     * 连接关闭回调（WebSocket）
     * @param WebSocketServer $server
     * @param                 $fd
     */
    protected function onWsClose(WebSocketServer $server, $fd)
    {

    }
}
