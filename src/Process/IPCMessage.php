<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Process;

use Generator;
use RuntimeException;

/**
 * Class IPCMessage
 * @package HZEX\TpSwoole\Process
 * TODO 需要预防内存泄漏问题
 */
class IPCMessage
{
    public const PROTOCOL_NEWS = 1;
    public const CHUNK_SIZE    = 65535;

    protected $ipcMessageId = 0;

    private const HEAD_LEN = 10;

    /**
     * @var string[]
     */
    private $bufferSet = [];

    /**
     * @param int    $workerId
     * @param string $payload
     * @return Generator
     */
    public function generateMsgChunk(int $workerId, string $payload): Generator
    {
        $messageId = ++$this->ipcMessageId;
        $payload_len = strlen($payload);
        $real_chunk_size = self::CHUNK_SIZE - self::HEAD_LEN;
        $chunk_id = 0;
        $send_size = 0;
        do {
            $send_payload = substr($payload, $chunk_id * $real_chunk_size, $real_chunk_size);
            $send_size += strlen($send_payload);
            $chunk_id++;
            if ($send_size > $payload_len) {
                throw new RuntimeException("wrong data transmission length {$send_size} > {$payload_len}");
            }
            $data = pack('CNNC', self::PROTOCOL_NEWS, $workerId, $messageId, $send_size === $payload_len);
            yield $data . $send_payload;
        } while ($send_size !== $payload_len);
    }

    /**
     * @param string $data
     * @return string|array|null
     */
    public function read(string $data)
    {
        $unpack = unpack('Cprotocol/Nwid/Nmid/Cdone', $data);
        if (!$unpack) {
            return false;
        }

        [
            'protocol' => $protocol
            , 'wid' =>  $worker_id
            , 'mid' => $message_id
            , 'done' => $done
        ] = $unpack;

        $key = "{$protocol}-{$worker_id}-{$message_id}";
        $payload= substr($data, self::HEAD_LEN);
        if (isset($this->bufferSet[$key])) {
            $this->bufferSet[$key] .= $payload;
        } else {
            $this->bufferSet[$key] = $payload;
        }

        if ($done) {
            $result = $this->bufferSet[$key];
            unset($this->bufferSet[$key]);
            if (self::PROTOCOL_NEWS === $protocol) {
                $result = ['ipc', $worker_id, $result];
            }
        } else {
            $result = null;
        }
        return $result;
    }
}
