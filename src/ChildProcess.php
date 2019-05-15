<?php

namespace HZEX\TpSwoole;

use Closure;
use Swoole\Coroutine;
use Swoole\Process;

abstract class ChildProcess
{
    /** @var Manager */
    protected $manager;
    /** @var Process */
    protected $process;

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
            0,
            true
        );
    }

    /**
     * 子进程
     * @param Process $process
     */
    protected function process(Process $process)
    {
        // 初始化子进程
        swoole_set_process_name('php-ps: ' . static::class);
        Process::signal(SIGTERM, function ($signal_num) {
            echo "signal call = $signal_num, #{$this->process->pid}\r\n";
        });

        // 检测主进程
        go(function () {
            while ($this->checkManagerProcess()) {
                Coroutine::sleep(0.1);
            }
        });

        // 业务代码
        $this->processBox($process);

        $process->exit(0);
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