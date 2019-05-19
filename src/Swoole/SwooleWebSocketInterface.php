<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Swoole;

use Swoole\Http\Request;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server as WsServer;

interface SwooleWebSocketInterface
{
    /**
     * 连接建立回调（WebSocket）
     * @param WsServer $server
     * @param Request  $request
     */
    public function onOpen(WsServer $server, Request $request): void;

    /**
     * 消息到达回调（WebSocket）
     * @param WsServer $server
     * @param Frame    $frame
     */
    public function onMessage(WsServer $server, Frame $frame): void;

    /**
     * 连接关闭回调（WebSocket）
     * @param WsServer        $server
     * @param                 $fd
     */
    public function onWsClose(WsServer $server, $fd): void;
}
