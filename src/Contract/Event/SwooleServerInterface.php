<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Contract\Event;

use Swoole\Http\Server as HttpServer;
use Swoole\Server;
use Swoole\WebSocket\Server as WsServer;

interface SwooleServerInterface extends SwooleEventInterface
{
    /**
     * 主进程启动
     * @param Server|HttpServer|WsServer $server
     */
    public function onStart($server): void;

    /**
     * 主进程结束
     * @param Server|HttpServer|WsServer $server
     */
    public function onShutdown($server): void;

    /**
     * 管理进程启动
     * @param Server|HttpServer|WsServer $server
     */
    public function onManagerStart($server): void;

    /**
     * 管理进程结束
     * @param Server|HttpServer|WsServer $server
     */
    public function onManagerStop($server): void;
}
