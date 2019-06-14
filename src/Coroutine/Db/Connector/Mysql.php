<?php

namespace HZEX\TpSwoole\Coroutine\Db\Connector;

use Exception;
use HZEX\TpSwoole\Coroutine\Db\PDO\Mysql as PDOMysql;
use PDOException;
use think\Cache;
use think\helper\Str;
use think\Log;

class Mysql extends \think\db\connector\Mysql
{
    public function __construct(Cache $cache, Log $log, array $config = [])
    {
        $config['builder'] = !empty($config['builder'])
            ? $config['builder']
            : \think\db\builder\Mysql::class;
        parent::__construct($cache, $log, $config);
    }

    protected function createPdo($dsn, $username, $password, $params)
    {
        return new PDOMysql($dsn, $username, $password, $params);
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
