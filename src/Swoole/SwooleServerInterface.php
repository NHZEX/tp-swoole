<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Swoole;

use Swoole\Http\Server as HttpServer;
use Swoole\Server;
use Swoole\WebSocket\Server as WsServer;

interface SwooleServerInterface
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

    /**
     * 工作进程启动（Worker/Task）
     * @param Server|HttpServer|WsServer $server
     * @param int                        $workerId
     */
    public function onWorkerStart($server, int $workerId): void;

    /**
     * 工作进程终止（Worker/Task）
     * @param Server|HttpServer|WsServer $server
     * @param int                        $workerId
     */
    public function onWorkerStop($server, int $workerId): void;

    /**
     * 工作进程终止（Worker/Task）
     * @param Server|HttpServer|WsServer $server
     * @param int                        $workerId
     */
    public function onWorkerExit($server, int $workerId): void;

    /**
     * 工作进程异常（Worker/Task）
     * @param Server|HttpServer|WsServer $server
     * @param int                        $workerId
     * @param int                        $workerPid
     * @param int                        $exitCode
     * @param int                        $signal
     */
    public function onWorkerError($server, int $workerId, int $workerPid, int $exitCode, int $signal): void;

    /**
     * 工作进程收到消息
     * @param Server|HttpServer|WsServer $server
     * @param int                        $srcWorkerId
     * @param mixed                      $message
     */
    public function onPipeMessage($server, int $srcWorkerId, $message): void;
}
