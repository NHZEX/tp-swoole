<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Process;

use Closure;
use HZEX\TpSwoole\Manager;
use Swoole\Coroutine;
use Swoole\Event;
use Swoole\Http\Server;
use Swoole\Process;
use Swoole\Timer;
use think\App;

abstract class ChildProcess implements ChildProcessInterface
{
    /**
     * @var bool 启用协程
     */
    protected $enableCoroutine = true;
    /**
     * @var App
     */
    protected $app;
    /**
     * @var Manager
     */
    protected $manager;
    /**
     * @var Server|\Swoole\Server|\Swoole\WebSocket\Server
     */
    protected $swoole;
    /**
     * @var Process
     */
    protected $process;
    /**
     * @var int
     */
    protected $pid;
    /**
     * @var int 退出码
     */
    protected $exitCode = 0;
    /**
     * @var int 重启计数
     */
    protected $reStart = 0;
    /**
     * @var bool 停止进程运行
     */
    protected $stopRun = false;
    /**
     * @var int
     */
    protected $checkMppid = 0;

    /**
     * @return bool
     */
    public static function isCoroutine()
    {
        return Coroutine::getCid() > 0;
    }

    public function __construct(App $app, Manager $manager)
    {
        $this->app = $app;
        $this->manager = $manager;
        $this->init();
    }

    protected function init()
    {
    }

    /**
     * @return string
     */
    public function pipeName(): string
    {
        return static::class;
    }

    /**
     * @return string
     */
    public function displayName(): string
    {
        return "{$this->pipeName()}({$this->process->pid})";
    }

    /**
     * @return Process
     */
    public function makeProcess(): Process
    {
        $this->reStart++;
        if ($this->process) {
            return $this->process;
        }
        return $this->process = new Process(
            Closure::fromCallable([$this, 'entrance']),
            false,
            SOCK_STREAM,
            $this->enableCoroutine
        );
    }

    /**
     * 是否保持运行
     * @return bool
     */
    protected function isKeepRun()
    {
        return false === $this->stopRun;
    }

    /**
     * 保持运行
     * @return void
     */
    protected function keepRun(): void
    {
        if (self::isCoroutine()) {
            while ($this->isKeepRun()) {
                Coroutine::sleep(1);
            }
        } else {
            Event::wait();
        }
        $this->stopRun = null;
    }

    protected function exit()
    {
        $this->process->exit(min($this->exitCode, 255));
    }

    /**
     * @return Process|null
     */
    public function getProcess(): ?Process
    {
        return $this->process;
    }

    /**
     * 停止进程运行
     * @param int|null $signal
     */
    protected function stop(?int $signal)
    {
        echo "child process {$this->displayName()} receive signal：{$signal}\n";

        $fun = function () {
            echo "child process {$this->displayName()}({$this->exitCode}) exit...\n";
            // 逻辑停止
            $this->stopRun = true;
            // 进程停止事件
            $this->processExit();
            echo "child process {$this->displayName()}({$this->exitCode}) stop\n";
            // 选择合适的退出方式
            if ($this->enableCoroutine) {
                $this->exit();
            } else {
                Event::exit();
            }
        };
        if ($this->enableCoroutine) {
            Coroutine::create($fun);
        } else {
            $fun();
        }
    }

    /**
     * 子进程
     * @param Process $process
     */
    protected function entrance(Process $process)
    {
        // TODO 无法启用全局协程化
        // \Swoole\Runtime::enableCoroutine(true);
        $this->swoole = $this->manager->getSwoole();
        // 记录进程ID
        $this->pid = $process->pid;
        // 调试信息
        echo "child process {$this->displayName()} run\n";
        // 初始化子进程
        $process->name('php ops-child: ' . static::class);

        // 响应 SIGINT ctrl+c
        Process::signal(SIGINT, Closure::fromCallable([$this, 'stop']));
        // 响应 SIGTERM
        Process::signal(SIGTERM, Closure::fromCallable([$this, 'stop']));

        // 监控主进程存活
        $this->checkMppid = Timer::tick(300, Closure::fromCallable([$this, 'checkManagerProcess']));

        $this->processMain($process);
        // 保持运行
        $this->keepRun();
    }

    /**
     * @param Process $process
     * @return bool
     */
    abstract protected function processMain(Process $process);

    /**
     * 进程停止
     */
    abstract protected function processExit(): void;

    /**
     * 检测主进程
     * @return true
     */
    public function checkManagerProcess()
    {
        $mpid = $this->swoole->master_pid;
        $process = $this->process;

        if (false == Process::kill($mpid, 0)) {
            echo "manager process [{$mpid}] exited, I [{$process->pid}] also quit\n";
            Process::kill($process->pid, SIGTERM);
            Timer::clear($this->checkMppid);
        }

        return true;
    }
}
