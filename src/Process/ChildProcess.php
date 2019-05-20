<?php

namespace HZEX\TpSwoole\Process;

use Closure;
use HZEX\TpSwoole\Manager;
use Swoole\Coroutine;
use Swoole\Process;

abstract class ChildProcess implements ChildProcessInterface
{
    /** @var Manager */
    protected $manager;
    /** @var Process */
    protected $process;
    /** @var int 退出码 */
    protected $exitCode = 0;
    /** @var int 重启计数 */
    protected $reStart = 0;

    /**
     * @return string
     */
    public function pipeName(): string
    {
        return static::class;
    }

    public function __construct(Manager $server)
    {
        $this->manager = $server;
        $this->init();
    }

    protected function init()
    {
    }

    /**
     * @return Process
     */
    public function makeProcess(): Process
    {
        return $this->process = new Process(
            Closure::fromCallable([$this, 'process']),
            false,
            SOCK_STREAM,
            false
        );
    }

    /**
     * @return Process|null
     */
    public function getProcess(): ?Process
    {
        return $this->process;
    }

    /**
     * 子进程
     * @param Process $process
     */
    protected function process(Process $process)
    {
        // 初始化子进程
        swoole_set_process_name('php-ps: ' . static::class);
        // 监听关闭信号
        Process::signal(SIGTERM, function ($signal_num) use ($process) {
            echo "signal call = $signal_num, #{$this->process->pid}\r\n";
            $process->exit($this->exitCode);
        });

        // 监控主进程存活
        go(function () {
            while ($this->checkManagerProcess()) {
                Coroutine::sleep(0.1);
            }
        });

        // 执行进程业务
        go(function () use ($process) {
            $ref = $this->processBox($process);
            $this->exitCode = null === $ref ? 0 : $ref;
            $process->exit($this->exitCode);
        });

        return;
    }

    /**
     * @param Process $process
     * @return bool
     */
    abstract protected function processBox(Process $process);

    /**
     * 检测主进程
     * @return true
     */
    public function checkManagerProcess()
    {
        $mpid = $this->manager->getSwoole()->master_pid;
        $process = $this->process;

        if (false == Process::kill($mpid, 0)) {
            echo "manager process [{$mpid}] exited, I [{$process->pid}] also quit\n";
            $process->exit();
        }

        return true;
    }
}