<?php
declare(strict_types=1);

namespace HZEX\TpSwoole;

use HZEX\TpSwoole\Command\ServerCommand;
use HZEX\TpSwoole\Tp\Orm\Db;
use HZEX\TpSwoole\Tp\Request;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Swoole\Http\Server as HttpServer;
use Swoole\Server as Server;
use Swoole\WebSocket\Server as WebsocketServer;
use think\App;
use unzxin\zswCore\Event as SwooleEvent;

class Service extends \think\Service
{
    /**
     * @var bool
     */
    protected $isWebsocket = false;
    /**
     * @var Server|HttpServer|WebsocketServer
     */
    protected static $server;
    /**
     * 服务注册
     */
    public function register()
    {
        if (false === exist_swoole()) {
            return false;
        }

        $this->isWebsocket = $this->app->config->get('swoole.websocket.enabled', false);

        // 绑定必须类
        $this->app->bind('swoole.server', function () {
            if (is_null(static::$server)) {
                $this->createSwooleServer();
            }
            return static::$server;
        });
        $this->app->bind(ContainerInterface::class, App::class);
        $this->app->bind('manager', Manager::class);
        $this->app->bind('request', Request::class);

        /** @var SwooleEvent $event */
        $event = $this->app->make(SwooleEvent::class);
        $event->setResolver(new EventResolver());

        // 替换默认db实现，以更好的兼任协程
        $this->app->bind('db', Db::class);
        return true;
    }

    /**
     * 服务启动
     */
    public function boot()
    {
        $this->commands(ServerCommand::class);
    }

    /**
     * @return HttpServer|Server|WebsocketServer
     */
    public static function getServer()
    {
        return self::$server;
    }

    /**
     * Create swoole server.
     */
    protected function createSwooleServer()
    {
        $config = $this->app->config->get('swoole.server');

        if (empty($config['listen'])) {
            $host = $config['host'];
            $port = $config['port'];
        } else {
            if (false === strpos($config['listen'], ':')) {
                $config['listen'] .= ':9501';
            }
            [$host, $port] = explode(':', $config['listen']);
        }

        if (false === filter_var($host, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException("rpc server listen host invalid: {$host}");
        }
        if (false === ctype_digit($port) || $port > 65535 || 1 > $port) {
            throw new InvalidArgumentException("rpc server listen port invalid: {$port}");
        }

        $socketType = $config['socket_type'] ?? SWOOLE_SOCK_TCP;
        $mode = $config['mode'] ?? SWOOLE_PROCESS;

        $server = $this->isWebsocket ? WebsocketServer::class : HttpServer::class;
        static::$server = new $server($host, (int) $port, $mode, $socketType);

        static::$server->set($config['options'] ?? []);
    }
}
