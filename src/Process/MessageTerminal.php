<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Process;

use Swoole\Process;

/**
 * Trait MessageTerminal
 * @package App\Process
 * @property Process $process
 */
trait MessageTerminal
{
    private function receiveMessage(string $data): bool
    {
        $payload = unserialize($data);
        if (is_array($payload)) {
            [$type, $from, $data] = $payload;
            if ('recv' === $type) {
                $this->onPipeMessage($data, $from);
                return true;
            }
        }

        return false;
    }

    abstract protected function onPipeMessage($data, ?string $form);

    /**
     * 命名管道消息发送
     * @param mixed  $data
     * @param string $to
     */
    protected function sendMessage($data, string $to = null)
    {
        $payload = serialize(['send', $to, $data]);
        $length = 4 + strlen($payload);
        $payload = pack('N', $length) . $payload;
        $this->process->write($payload);
    }
}
