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
     * @param Task                $task
     * @return null|mixed
     */
    public function onTask($server, Task $task);

    /**
     * 任务完成响应
     * @param HttpServer|WsServer $server
     * @param int                 $taskId
     * @param mixed               $data
     */
    public function onFinish($server, int $taskId, $data): void;
}
