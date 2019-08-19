<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\ConnectionPool;

use Swoole\Coroutine\MySQL;

class CoroutineMySQLConnector extends \Smf\ConnectionPool\Connectors\CoroutineMySQLConnector
{
    /**
     * @param MySQL $connection
     * @return bool
     */
    public function isConnected($connection): bool
    {
        return $connection->connected && (false !== $connection->query('select true;'));
    }
}
