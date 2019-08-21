<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Contract\Event;

use Swoole\Http\Server as HttpServer;
use Swoole\Server;
use Swoole\WebSocket\Server as WsServer;

interface SwoolePipeMessageInterface extends SwooleEventInterface
{
    /**
     * 工作进程收到消息
     * @param Server|HttpServer|WsServer $server
     * @param int                        $srcWorkerId
     * @param mixed                      $message
     */
    public function onPipeMessage($server, int $srcWorkerId, $message): void;
}
