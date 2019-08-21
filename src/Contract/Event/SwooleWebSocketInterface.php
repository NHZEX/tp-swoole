<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Contract\Event;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server as WsServer;

interface SwooleWebSocketInterface extends SwooleEventInterface
{
    /**
     * 连接握手回调（WebSocket）
     * @param Request  $request
     * @param Response $response
     */
    public function onHandShake(Request $request, Response $response): void;

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
     * @param int             $reactorId
     */
    public function onClose(WsServer $server, $fd, int $reactorId): void;
}
