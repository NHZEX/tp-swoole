<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Concerns;

use HZEX\TpSwoole\PidManager;
use Psr\Log\LoggerInterface;
use Swoole\Http\Server as HttpServer;
use Swoole\Server;
use Swoole\Timer;
use Swoole\WebSocket\Server as WsServer;
use unzxin\zswCore\Event;

/**
 * Trait InteractsWithServer
 * @package HZEX\TpSwoole\Concerns
 * @method Event getEvent()
 * @property PidManager $pidManager
 * @property LoggerInterface $logger
 */
trait InteractsWithWorker
{
    /**
     * 工作进程启动（Worker/Task）
     * @param HttpServer|Server|WsServer $server
     * @param int                        $workerId
     */
    public function onWorkerStart($server, int $workerId): void
    {
        $type = $server->taskworker ? 'task' : 'worker';
        // 输出调试信息
        $this->logger->info("{$type} start\t#{$workerId}({$server->worker_pid})");
        // 设置进程名称
        $this->setProcessName("{$type}#{$workerId}");
        // 事件触发
        $this->getEvent()->trigSwooleWorkerStart(func_get_args());
    }

    /**
     * 工作进程终止（Worker/Task）
     * @param     $server
     * @param int $workerId
     */
    public function onWorkerStop($server, int $workerId): void
    {
        $type = $server->taskworker ? 'task' : 'worker';
        $this->logger->info("{$type} stop\t#{$workerId}({$server->worker_pid})");
        // 事件触发
        $this->getEvent()->trigSwooleWorkerStop(func_get_args());
    }

    /**
     * 工作进程退出（Worker/Task）
     * @param     $server
     * @param int $workerId
     */
    public function onWorkerExit($server, int $workerId): void
    {
        $type = $server->taskworker ? 'task' : 'worker';
        $this->logger->info("{$type} exit\t#{$workerId}({$server->worker_pid})");
        // 事件触发
        $this->getEvent()->trigSwooleWorkerExit(func_get_args());
        // 清理全部定时器
        Timer::clearAll();
    }

    /**
     * 工作进程异常（Worker/Task）
     * @param HttpServer|Server|WsServer $server
     * @param int                        $workerId
     * @param int                        $workerPid
     * @param int                        $exitCode
     * @param int                        $signal
     */
    public function onWorkerError($server, int $workerId, int $workerPid, int $exitCode, int $signal): void
    {
        $this->logger->error("WorkerError: $workerId, pid: $workerPid, execCode: $exitCode, signal: $signal");
        // 事件触发
        $this->getEvent()->trigSwooleWorkerError(func_get_args());
    }

    /**
     * 工作进程收到消息
     * @param HttpServer|WsServer $server
     * @param int                 $srcWorkerId
     * @param mixed               $message
     */
    public function onPipeMessage($server, int $srcWorkerId, $message): void
    {
        // 事件触发
        $this->getEvent()->trigSwoolePipeMessage(func_get_args());
    }
}
