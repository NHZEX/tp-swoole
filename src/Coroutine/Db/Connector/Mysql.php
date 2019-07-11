<?php

namespace HZEX\TpSwoole\Coroutine\Db\Connector;

use Co;
use Exception;
use HZEX\TpSwoole\Coroutine\Db\PDO\Mysql as PDOMysql;
use PDO;
use PDOException;
use Smf\ConnectionPool\BorrowConnectionTimeoutException;
use think\helper\Str;

class Mysql extends \think\db\connector\Mysql
{
    protected $isSwoole = false;

    /**
     * 架构函数 读取数据库配置信息
     * @access public
     * @param array $config 数据库配置数组
     */
    public function __construct(array $config = [])
    {
        $config['builder'] = !empty($config['builder'])
            ? $config['builder']
            : \think\db\builder\Mysql::class;

        parent::__construct($config);
        $this->isSwoole = exist_swoole();
    }

    /**
     * @param $dsn
     * @param $username
     * @param $password
     * @param $params
     * @return PDOMysql|PDO
     * @throws BorrowConnectionTimeoutException
     */
    protected function createPdo($dsn, $username, $password, $params)
    {
        if (false === $this->isSwoole || -1 === Co::getCid()) {
            return parent::createPdo($dsn, $username, $password, $params);
        }
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
        if (-1 === Co::getCid()) {
            return parent::isBreak($e);
        }

        if (!$this->config['break_reconnect']) {
            return false;
        }

        return parent::isBreak($e) || Str::contains($e->getMessage(), 'is closed');
    }
}
