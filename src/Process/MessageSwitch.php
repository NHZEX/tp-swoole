<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Process;

use Closure;

class MessageSwitch
{
    /** @var ChildProcessInterface[] */
    private $messageTermina = [];
    /** @var Closure */
    private $onMessage = null;


    public function setRecvMessage(Closure $closure)
    {
        $this->onMessage = $closure;
    }

    public function add($name, $object)
    {
        $this->messageTermina[$name] = $object;
    }

    public function del($name)
    {
        unset($this->messageTermina[$name]);
    }

    /**
     * @param string $data
     * @param string $form
     * @return bool
     * @throws MessageSwitchException
     */
    public function receiveMessage(string $data, string $form): bool
    {
        $payload = unserialize($data);
        if (is_array($payload)) {
            if ($this->switchMessage($payload, $form)) {
                return true;
            };
        }
        return false;
    }

    /**
     * @param array  $payload 内容
     * @param string $form    来自
     * @return bool|void
     * @throws MessageSwitchException
     */
    private function switchMessage(array $payload, ?string $form)
    {
        [$type, $to, $data] = $payload;
        if ('send' === $type) {
            if (empty($to)) {
                // 发送给自己
                if (is_callable($this->onMessage)) {
                    call_user_func($this->onMessage, $data, $form);
                }
                return true;
            }
            // 不存在的接收方
            if (false === isset($this->messageTermina[$to])) {
                throw new MessageSwitchException("欲送达消息 {$to} 终端不存在");
            }

            // 转发到接收方
            $this->messageTermina[$to]->getProcess()->write($this->recvMessagePack($data, $form));
            return true;
        }

        return false;
    }

    /**
     * @param mixed       $data
     * @param string|null $form
     * @return string
     */
    private function recvMessagePack($data, string $form = null): string
    {
        $payload = serialize(['recv', $form, $data]);
        $length = 4 + strlen($payload);
        $payload = pack('N', $length) . $payload;
        return $payload;
    }

    /**
     * 命名管道消息发送
     * @param mixed       $data
     * @param string|null $to
     * @throws MessageSwitchException
     */
    public function sendMessage($data, string $to = null)
    {
        $this->switchMessage(['send', $to, $data], null);
    }
}
