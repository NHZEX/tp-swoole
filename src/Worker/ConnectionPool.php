<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Worker;

use Closure;
use HZEX\TpSwoole\ConnectionPool\CoroutineMySQLConnector;
use HZEX\TpSwoole\Manager;
use Smf\ConnectionPool\ConnectionPool as SmfConnectionPool;
use Smf\ConnectionPool\ConnectionPoolTrait;
use Smf\ConnectionPool\Connectors\PhpRedisConnector;
use Swoole\Http\Server as HttpServer;
use Swoole\Server;
use Swoole\WebSocket\Server as WsServer;
use think\Config;
use unzxin\zswCore\Contract\Events\SwooleWorkerInterface;
use unzxin\zswCore\Contract\EventSubscribeInterface;
use unzxin\zswCore\Event;

class ConnectionPool implements WorkerPluginContract, SwooleWorkerInterface, EventSubscribeInterface
{
    use ConnectionPoolTrait;

    private $config = [
        'host' => '127.0.0.1',
        'port' => '6379',
        'password' => '',
        'select' => 0,
        'timeout' => 1,
    ];

    public function __construct(Config $config)
    {
        $this->config = $config->get('redis', []) + $this->config;
    }

    /**
     * 插件是否就绪
     * @param Manager $manager
     * @return bool
     */
    public function isReady(Manager $manager): bool
    {
        return true;
    }

    /**
     * 插件准备启动
     * @param Manager $manager
     * @return bool
     */
    public function prepare(Manager $manager): bool
    {
        return true;
    }

    public function subscribe(Event $event): void
    {
        $event->onSwooleWorkerStart(Closure::fromCallable([$this, 'onWorkerStart']));
        $event->onSwooleWorkerStop(Closure::fromCallable([$this, 'onWorkerStop']));
        $event->onSwooleWorkerError(Closure::fromCallable([$this, 'onWorkerError']));
    }

    /**
     * @param array  $options
     * @param string $name
     * @return SmfConnectionPool
     */
    public function requestMysql(array $options, ?string &$name): SmfConnectionPool
    {
        // 生成唯一命名
        $name = hash('sha1', serialize($options));
        // 获取连接池
        if (false === $this->hasConnectionPool($name)) {
            // All MySQL connections: [4 workers * 2 = 8, 4 workers * 10 = 40]
            $smfMysqlPool = new SmfConnectionPool(
                [
                    'minActive' => 2,
                    'maxActive' => 10,
                ],
                new CoroutineMySQLConnector,
                $options
            );
            $smfMysqlPool->init();
            $this->addConnectionPool($name, $smfMysqlPool);
        } else {
            $smfMysqlPool = $this->getConnectionPool($name);
        }

        return $smfMysqlPool;
    }

    /**
     * @param array  $options
     * @param string $name
     * @return SmfConnectionPool
     */
    public function requestRedis(array $options, ?string &$name): SmfConnectionPool
    {
        // 生成唯一命名
        $name = hash('sha1', serialize($options));
        // 获取连接池
        if (false === $this->hasConnectionPool($name)) {
            // All Redis connections: [4 workers * 5 = 20, 4 workers * 20 = 80]
            $smfRedisPool = new SmfConnectionPool(
                [
                    'minActive' => 5,
                    'maxActive' => 20,
                ],
                new PhpRedisConnector,
                $options
            );
            $smfRedisPool->init();
            $this->addConnectionPool($name, $smfRedisPool);
        } else {
            $smfRedisPool = $this->getConnectionPool($name);
        }
        return $smfRedisPool;
    }

    /**
     * 连接池是否存在
     * @param string $key
     * @return bool
     */
    public function hasConnectionPool(string $key): bool
    {
        return isset($this->pools[$key]);
    }

    /**
     * 工作进程启动（Worker/Task）
     * @param Server|HttpServer|WsServer $server
     * @param int                        $workerId
     */
    public function onWorkerStart($server, int $workerId): void
    {
        if ($server->taskworker) {
            return;
        }
    }

    /**
     * 工作进程终止（Worker/Task）
     * @param Server|HttpServer|WsServer $server
     * @param int                        $workerId
     */
    public function onWorkerStop($server, int $workerId): void
    {
        if ($server->taskworker) {
            return;
        }
        $this->closeConnectionPools();
    }

    /**
     * 工作进程退出（Worker）
     * @param Server|HttpServer|WsServer $server
     * @param int                        $workerId
     */
    public function onWorkerExit($server, int $workerId): void
    {
        $this->closeConnectionPools();
    }

    /**
     * 工作进程异常（Worker/Task）
     * @param Server|HttpServer|WsServer $server
     * @param int                        $workerId
     * @param int                        $workerPid
     * @param int                        $exitCode
     * @param int                        $signal
     */
    public function onWorkerError($server, int $workerId, int $workerPid, int $exitCode, int $signal): void
    {
        $this->closeConnectionPools();
    }
}
