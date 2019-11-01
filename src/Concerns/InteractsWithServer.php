<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Concerns;

use HZEX\TpSwoole\PidManager;
use Psr\Log\LoggerInterface;
use Swoole\Http\Server as HttpServer;
use Swoole\Process;
use Swoole\Runtime;
use Swoole\Server;
use Swoole\WebSocket\Server as WsServer;
use unzxin\zswCore\Event;

/**
 * Trait InteractsWithServer
 * @package HZEX\TpSwoole\Concerns
 * @method Event getEvent()
 * @property PidManager $pidManager
 * @property LoggerInterface $logger
 */
trait InteractsWithServer
{
    /**
     * 主进程启动
     * @param HttpServer|Server|WsServer $server
     */
    public function onStart($server): void
    {
        $this->pidManager->create($server->master_pid, $server->manager_pid ?? 0);
        // 输出调试信息
        $this->logger->info("master start\t#{$server->master_pid}");
        // 设置进程名称
        $this->setProcessName('master');
        // 响应终端 ctrl+c
        Process::signal(SIGINT, function () use ($server) {
            echo PHP_EOL;
            $server->shutdown();
        });
        // 全局协程
        Runtime::enableCoroutine(
            $this->getConfig('coroutine.enable', true),
            $this->getConfig('coroutine.flags', SWOOLE_HOOK_ALL)
        );
        // 事件触发
        $this->getEvent()->trigSwooleStart(func_get_args());
    }

    /**
     * 主进程结束
     * @param HttpServer|WsServer $server
     */
    public function onShutdown($server): void
    {
        // 输出调试信息
        $this->logger->info("master shutdown\t#{$server->master_pid}");
        // 事件触发
        $this->getEvent()->trigSwooleShutdown(func_get_args());
    }

    /**
     * 管理进程启动
     * @param HttpServer|Server|WsServer $server
     */
    public function onManagerStart($server): void
    {
        // 输出调试信息
        $this->logger->info("manager start\t#{$server->manager_pid}");
        // 设置进程名称
        $this->setProcessName('manager');
        // 事件触发
        $this->getEvent()->trigSwooleManagerStart(func_get_args());
    }

    /**
     * 管理进程结束
     * @param HttpServer|WsServer $server
     */
    public function onManagerStop($server): void
    {
        // 输出调试信息
        $this->logger->info("manager stop\t#{$server->manager_pid}");
        // 事件触发
        $this->getEvent()->trigSwooleManagerStop(func_get_args());
    }
}
