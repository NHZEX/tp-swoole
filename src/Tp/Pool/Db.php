<?php

namespace HZEX\TpSwoole\Tp\Pool;

use HZEX\TpSwoole\Concerns\InteractsWithPool;
use HZEX\TpSwoole\Tp\Pool\Db\Connection;
use RuntimeException;
use Swoole\Coroutine;
use think\Config;
use think\db\ConnectionInterface;

/**
 * Class Db
 * @package think\swoole\pool
 * @property Config $config
 */
class Db extends \think\Db
{
    use InteractsWithPool;

    protected function getMaxActive()
    {
        return $this->config->get('swoole.pool.db.max_active', 3);
    }

    protected function getMaxWaitTime()
    {
        return $this->config->get('swoole.pool.db.max_wait_time', 5);
    }

    /**
     * 创建数据库连接实例
     * @access protected
     * @param string|null $name  连接标识
     * @param bool        $force 强制重新连接
     * @return ConnectionInterface
     */
    protected function instance(string $name = null, bool $force = false): ConnectionInterface
    {
        if (empty($name)) {
            $name = $this->getConfig('default', 'mysql');
        }

        if (!exist_swoole() || -1 === Coroutine::getCid()) {
            return parent::instance(...func_get_args());
        }

        $key = "db.connection.{$name}";
        $cxt = Coroutine::getContext();
        if (isset($cxt[$key])) {
            return $cxt[$key];
        }
        $pool = function () use ($force, $name) {

            $pool = $this->getPool($name);

            if (!isset($this->connectionCount[$name])) {
                $this->connectionCount[$name] = 0;
            }

            if ($this->connectionCount[$name] < $this->getMaxActive()) {
                //新建
                if (!$force) {
                    $this->connectionCount[$name]++;
                }
                return new Connection($this->createConnection($name), $pool, !$force);
            }

            $connection = $pool->pop($this->getMaxWaitTime());

            if ($connection === false) {
                throw new RuntimeException(sprintf(
                    'Borrow the connection timeout in %.2f(s), connections in pool: %d, all connections: %d',
                    $this->getMaxWaitTime(),
                    $pool->length(),
                    $this->connectionCount[$name] ?? 0
                ));
            }

            return new Connection($connection, $pool);
        };

        return $cxt[$key] = $pool();
    }

    protected function getConnectionConfig(string $name): array
    {
        $config = parent::getConnectionConfig($name);

        //打开断线重连
        $config['break_reconnect'] = true;
        return $config;
    }

}
