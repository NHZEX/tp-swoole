<?php

namespace HZEX\TpSwoole\Coroutine\Db\Connector;

use Exception;
use HZEX\TpSwoole\Coroutine\Db\PDO;
use PDOException;
use think\helper\Str;

class Mysql extends \think\db\connector\Mysql
{
    protected $builderClassName = \think\db\builder\Mysql::class;

    protected function createPdo($dsn, $username, $password, $params)
    {
        return new PDO($dsn, $username, $password, $params);
    }

    /**
     * 是否断线
     * @access protected
     * @param PDOException|Exception $e 异常对象
     * @return bool
     */
    protected function isBreak($e): bool
    {
        if (!$this->config['break_reconnect']) {
            return false;
        }

        return parent::isBreak($e) || Str::contains($e->getMessage(), 'is closed');
    }
}
