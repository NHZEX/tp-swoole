<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Contract\Event;

use Swoole\Server;

interface SwooleServerTcpInterface extends SwooleEventInterface
{
    /**
     * 连接进入（Tcp）
     * @param Server $server
     * @param int    $fd
     * @param int    $reactorId
     */
    public function onConnect(Server $server, int $fd, int $reactorId): void;

    /**
     * 收到数据（Tcp）
     * @param Server $server
     * @param int    $fd
     * @param int    $reactorId
     * @param string $data
     */
    public function onReceive(Server $server, int $fd, int $reactorId, string $data): void;

    /**
     * 连接关闭（Tcp）
     * @param Server $server
     * @param int    $fd
     * @param int    $reactorId
     */
    public function onClose(Server $server, int $fd, int $reactorId): void;
}
