<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Process;

use Closure;
use Co;
use Exception;
use HZEX\TpSwoole\Manager;
use HZEX\TpSwoole\ProcessPool;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Swoole\Coroutine;
use Swoole\Process;
use Swoole\Timer;
use function HuangZx\debug_string;

abstract class BaseSubProcess implements SubProcessInterface
{
    /**
     * @var int 工作ID
     */
    protected $workerId;
    /**
     * @var Manager
     */
    protected $manager;
    /**
     * @var ProcessPool
     */
    protected $pool;
    /**
     * @var IPCMessage
     */
    private $pipeBuffer;
    /**
     * @var bool
     */
    protected $running = true;
    /**
     * @var bool
     */
    protected $debug = false;
    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var Process
     */
    protected $process;
    /**
     * @var int
     */
    protected $checkMppidTime = 0;

    public function __construct()
    {
        $this->pipeBuffer = new IPCMessage();
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
    protected function displayName(): string
    {
        return "{$this->pipeName()}({$this->process->pid})";
    }

    /**
     * @param int $workerId
     */
    public function setWorkerId(int $workerId): void
    {
        $this->workerId = $workerId;
    }

    /**
     * @return int
     */
    public function getWorkerId(): int
    {
        return $this->workerId;
    }

    /**
     * @return Process
     */
    public function getProcess(): Process
    {
        return $this->process;
    }

    /**
     * @param bool $debug
     * @return $this
     */
    public function setDebug(bool $debug): self
    {
        $this->debug = $debug;
        return $this;
    }

    /**
     * @param LoggerInterface $logger
     * @return $this
     */
    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * @param Manager     $manager
     * @param ProcessPool $pool
     * @return Process
     */
    public function makeProcess(Manager $manager, ProcessPool $pool): Process
    {
        if ($this->process) {
            return $this->process;
        }
        $this->manager = $manager;
        $this->pool = $pool;
        return $this->process = new Process(
            Closure::fromCallable([$this, 'entrance']),
            false,
            SOCK_STREAM,
            true
        );
    }

    /**
     * 进程入口
     */
    protected function entrance()
    {
        $this->process->name("php-opsc: {$this->workerId}#{$this->displayName()}");
        $this->manager->getOutput()->info("child process {$this->workerId}#{$this->displayName()} run");

        // 响应 SIGINT ctrl+c
        Process::signal(SIGINT, Closure::fromCallable([$this, 'stop']));
        // 响应 SIGTERM
        Process::signal(SIGTERM, Closure::fromCallable([$this, 'stop']));

        // 监控主进程存活
        $this->checkMppidTime = Timer::tick(500, Closure::fromCallable([$this, 'checkManagerProcess']));

        $this->listenPipeMessage();

        $this->worker();
    }

    /**
     * 检测主进程
     * @return true
     */
    public function checkManagerProcess()
    {
        $mpid = $this->pool->getMasterPid();

        if (false == Process::kill($mpid, 0)) {
            $this->manager->getOutput()->warning("manager process [{$mpid}] exited, {$this->displayName()} also quit");
            Process::kill($this->process->pid, SIGTERM);
            Timer::clear($this->checkMppidTime);
        }

        return true;
    }

    /**
     * 进程停止运行
     * @param int|null $signal
     */
    protected function stop(?int $signal)
    {
        $output = $this->manager->getOutput();
        $output->info("child process {$this->displayName()} receive signal：{$signal}");
        $this->running = false;

        Co::create(function () use ($signal, $output) {
            $output->info("child process {$this->displayName()} exit...");

            $this->onExit();

            // 等待所有协程退出
            $waitTime = microtime(true) + 8;
            foreach (Coroutine::list() as $cid) {
                $output->info("wait coroutine #{$cid} exit...");
                if ($cid === Coroutine::getCid()) {
                    continue;
                }
                // TODO 需要引入协程中断
                while (Coroutine::exists($cid) && microtime(true) < $waitTime) {
                    Coroutine::sleep(0.1);
                }
                if (microtime(true) > $waitTime) {
                    // 协程退出超时
                    if ($bt = Coroutine::getBackTrace($cid, DEBUG_BACKTRACE_IGNORE_ARGS, 1)) {
                        $info = array_pop($bt);
                        $info['file'] = $info['file'] ?? 'null';
                        $info['line'] = $info['line'] ?? 'null';
                        $info['function'] = $info['function'] ?? 'null';
                        $message = "{$info['file']}:{$info['line']}#{$info['function']}";
                        $output->warning("coroutine #{$cid} time out: {$message}");
                    } else {
                        $output->warning("coroutine #{$cid} time out: does not exist");
                    }
                }
            }

            $output->info("child process {$this->displayName()} exit");
            $this->process->exit();
        });
    }

    protected function listenPipeMessage()
    {
        Co::create(function () {
            $output = $this->manager->getOutput();
            $socket = $this->process->exportSocket();

            while ($this->running) {
                if (!$recv = $socket->recv(IPCMessage::CHUNK_SIZE, 2.0)) {
                    if (0 !== $socket->errCode && $socket->errCode !== SOCKET_ETIMEDOUT) {
                        $output->error("socket recv error: ({$socket->errCode}){{$socket->errMsg}}");
                    }
                    continue;
                }
                // 从解析器中获取数据帧
                if ($payload = $this->pipeBuffer->read($recv)) {
                    // 业务处理
                    if (is_array($payload) && $this->recvMessage($payload)) {
                        continue;
                    };
                    if ($this->onPipeMessage($payload, null)) {
                        continue;
                    };
                    $payload = substr($payload, 0, 64);
                    $output->warning("Unable process message: $payload");
                }
            }
        });
    }

    /**
     * 解析消息
     * @param array $data
     * @return bool
     */
    private function recvMessage(array $data): bool
    {
        $output = $this->manager->getOutput();
        try {
            [, $from, $payload] = $data;
            $payload = unserialize($payload);
        } catch (Exception $exception) {
            $message = $exception->getMessage();
            $output->warning("ipc message unserialize failure: " . $message);
            if (preg_match_all('/unserialize\(\): Error at offset (\d+) of (\d+) bytes/m', $message, $matches)) {
                $message = debug_string($payload, (int) $matches[2][0], 32);
                $message = "unserialize error at offset {$matches[1][0]} of: ({$matches[2][0]}) -> {$message}";
                $output->error($message);
            }
            return false;
        }
        $this->onPipeMessage($payload, $this->pool->getWorkerName($from));
        return true;
    }

    /**
     * 管道消息发送
     * @param mixed  $data
     * @param string $pipeName
     */
    protected function sendMessage($data, string $pipeName)
    {
        $data = serialize($data);
        $socker = $this->pool->getWorkerSocket($pipeName);
        foreach ($this->pipeBuffer->generateMsgChunk($this->workerId, $data) as $chunk) {
            $send_len = $socker->sendAll($chunk);
            if (false === $send_len) {
                throw new RuntimeException("data transmission failed: ({$socker->errCode}){$socker->errMsg}");
            }
            $len = strlen($chunk);
            if ($send_len > $len) {
                throw new RuntimeException("wrong data chunk transmission length {$len} !== {$send_len}");
            }
        }
    }

    /**
     * 收到消息事件
     * @param        $data
     * @param string $form
     * @return bool
     */
    abstract protected function onPipeMessage($data, ?string $form): bool;

    /**
     * 进程退出
     */
    abstract protected function onExit(): void;

    /**
     * 自定义实现
     */
    abstract protected function worker();
}
