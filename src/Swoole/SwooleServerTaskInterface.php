<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Swoole;

use Swoole\Http\Server as HttpServer;
use Swoole\Server\Task;
use Swoole\WebSocket\Server as WsServer;

interface SwooleServerTaskInterface
{
    /**
     * 任务处理回调
     * @param HttpServer|WsServer $server
     * @param int|Task            $taskId
     * @param int                 $srcWorkerId
     * @param mixed               $data
     * @return null|mixed
     */
    public function onTask($server, $taskId, int $srcWorkerId, $data);

    /**
     * 任务完成响应
     * @param HttpServer|WsServer $server
     * @param int                 $taskId
     * @param mixed               $data
     */
    public function onFinish($server, int $taskId, $data): void;
}
