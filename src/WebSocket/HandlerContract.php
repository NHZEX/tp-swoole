<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

namespace HZEX\TpSwoole\WebSocket;

use Swoole\Http\Request;
use Swoole\Websocket\Frame;
use Swoole\WebSocket\Server as WsServer;

interface HandlerContract
{
    /**
     * @param WsServer $server
     * @param int      $workerId
     */
    public function onStart(WsServer $server, int $workerId);

    /**
     * "onOpen" listener.
     *
     * @param WsServer             $server
     * @param Request $request
     */
    public function onOpen(WsServer $server, Request $request);

    /**
     * "onMessage" listener.
     *  only triggered when event handler not found
     *
     * @param WsServer $server
     * @param Frame    $frame
     */
    public function onMessage(WsServer $server, Frame $frame);

    /**
     * "onClose" listener.
     *
     * @param WsServer $server
     * @param int      $fd
     * @param int      $reactorId
     */
    public function onClose(WsServer $server, int $fd, int $reactorId);
}
