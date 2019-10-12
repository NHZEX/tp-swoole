<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Tp\Orm;

use InvalidArgumentException;
use Swoole\Coroutine;
use think\db\ConnectionInterface;

class Db extends \think\Db
{
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

        if ($force || !$this->hasInstance($name)) {
            $connections = $this->getConfig('connections');
            if (!isset($connections[$name])) {
                throw new InvalidArgumentException('Undefined db config:' . $name);
            }

            $config = $connections[$name];
            $type   = !empty($config['type']) ? $config['type'] : 'mysql';

            if (false !== strpos($type, '\\')) {
                $class = $type;
            } else {
                $class = '\\think\\db\\connector\\' . ucfirst($type);
            }

            $this->setInstance($name, new $class($config));
        }

        return $this->getInstance($name);
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasInstance(string $name)
    {
        if (-1 === Coroutine::getCid()) {
            return isset($this->instance[$name]);
        }
        $cxt = Coroutine::getContext();
        return isset($cxt['__orm_instance'][$name]);
    }

    /**
     * @param string     $name
     * @param Connection $connection
     * @return void
     */
    public function setInstance(string $name, Connection $connection)
    {
        if (-1 === Coroutine::getCid()) {
            $this->instance[$name] = $connection;
        }
        $cxt = Coroutine::getContext();
        $cxt['__orm_instance'][$name] = $connection;
    }

    /**
     * @param string $name
     * @return Connection
     */
    public function getInstance(string $name): Connection
    {
        if (-1 === Coroutine::getCid()) {
            return $this->instance[$name];
        }
        $cxt = Coroutine::getContext();
        return $cxt['__orm_instance'][$name];
    }
}
