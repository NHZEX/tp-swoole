<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Swoole;

use Swoole\Server;

interface SwooleServerUdpInterface
{
    /**
     * 数据包到达回调（Udp）
     * @param Server $server
     * @param string $data
     * @param array  $clientInfo
     */
    public function onPacket(Server $server, string $data, array $clientInfo): void;
}
