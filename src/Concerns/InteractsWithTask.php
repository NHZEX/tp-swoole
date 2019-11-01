<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Concerns;

use HZEX\TpSwoole\Contract\TaskInterface;
use HZEX\TpSwoole\PidManager;
use Psr\Log\LoggerInterface;
use Swoole\Http\Server as HttpServer;
use Swoole\Server;
use Swoole\WebSocket\Server as WsServer;
use unzxin\zswCore\Event;

/**
 * Class InteractsWithHttp
 * @package HZEX\TpSwoole\Concerns
 * @method Event getEvent()
 * @property PidManager $pidManager
 * @property LoggerInterface $logger
 */
trait InteractsWithTask
{
    /**
     * 任务处理回调
     * @param Server      $server
     * @param Server\Task $task
     */
    public function onTask($server, Server\Task $task)
    {
        if (!is_array($task->data)
            || count($task->data) !== 2
            || !in_array($task->data[0], $this->tasks, true)
        ) {
            $this->getEvent()->trigSwooleTask(func_get_args());
        }
        [$action, $data] = $task->data;
        /** @var TaskInterface $taskHandle */
        $taskHandle = new $action($server, $task);
        $result = $taskHandle->handle($data);
        //完成任务，结束并返回数据
        $task->finish($result);
    }

    /**
     * 任务完成响应
     * @param HttpServer|WsServer $server
     * @param int                 $taskId
     * @param string              $data
     */
    public function onFinish($server, int $taskId, $data): void
    {
        // 未触发事件
        $this->getEvent()->trigSwooleFinish(func_get_args());
    }
}
