<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Concerns;

use Closure;
use HZEX\TpSwoole\Process\ChildProcessInterface;
use HZEX\TpSwoole\Process\FrameProtocol;
use HZEX\TpSwoole\Process\MessageSwitch;
use Swoole\Event;
use Swoole\Http\Server as HttpServer;
use Swoole\Server;
use Swoole\WebSocket\Server as WsServer;

/**
 * Trait MessageSwitch
 * @package HZEX\TpSwoole\Concerns
 * @property Server|HttpServer|WsServer $swoole
 */
trait MessageSwitchTrait
{
    /** @var ChildProcessInterface[] */
    private $initChildProcess = [];
    /** @var ChildProcessInterface[] */
    private $childPipes = [];
    /** @var MessageSwitch */
    private $messageSwitch;
    /** @var FrameProtocol[] */
    private $pipeRecvBuffer = [];

    protected function initMessageSwitch()
    {
        // 初始化消息交换
        $this->messageSwitch = new MessageSwitch();
        $this->messageSwitch->setRecvMessage(Closure::fromCallable([$this, 'onPipeMessage']));
    }

    /**
     * 挂载用户进程
     */
    protected function mountProcess()
    {
        foreach ($this->initChildProcess as $child) {
            // 初始进程对象
            $process = $child->makeProcess();
            // 监听进程通信管道
            Event::add($process->pipe, function (int $pipe) {
                $child = $this->childPipes[$pipe];
                $process = $child->getProcess();

                $buffer = $this->pipeRecvBuffer[$pipe];

                // 写流数据到协议解析器
                $buffer->write($process->read());
                // 从解析器中获取数据帧
                while (false === $buffer->eof()) {
                    $payload = $buffer->read();
                    // 业务处理
                    if ($this->messageSwitch->receiveMessage($payload, $child->pipeName())) {
                        continue;
                    };
                    echo "Unable process message: $payload\n";
                }
            });
            $this->childPipes[$process->pipe] = $child;
            $this->pipeRecvBuffer[$process->pipe] = new FrameProtocol();
            $this->messageSwitch->add($child->pipeName(), $child);

            $this->swoole->addProcess($process);
        }
    }
}
