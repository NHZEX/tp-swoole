<?php
declare(strict_types=1);

namespace HZEX\TpSwoole;

use HZEX\TpSwoole\Command\ServerCommand;
use HZEX\TpSwoole\Tp\Log;
use HZEX\TpSwoole\Tp\Request;
use Swoole\Http\Server as HttpServer;
use Swoole\Runtime;
use Swoole\Server as Server;
use Swoole\WebSocket\Server as WebsocketServer;

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
        Runtime::enableCoroutine($this->app->config->get('swoole.enable_coroutine', false));

        // 绑定必须类
        $this->app->bind('swoole.server', function () {
            if (is_null(static::$server)) {
                $this->createSwooleServer();
            }
            return static::$server;
        });
        $this->app->bind('manager', Manager::class);
        $this->app->bind('request', Request::class);

        // 替换默认日志实现
        if ($this->app->log instanceof \think\Log) {
            $this->app->instance(\think\Log::class, $this->app->make(Log::class));
        }
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
     * Create swoole server.
     */
    protected function createSwooleServer()
    {
        $config = $this->app->config;

        $host = $config->get('swoole.server.host');
        $port = $config->get('swoole.server.port');
        $socketType = $config->get('swoole.server.socket_type', SWOOLE_SOCK_TCP);
        $mode = $config->get('swoole.server.mode', SWOOLE_PROCESS);

        $server = $this->isWebsocket ? WebsocketServer::class : HttpServer::class;
        static::$server = new $server($host, $port, $mode, $socketType);

        $options = $config->get('swoole.server.options');

        static::$server->set($options);
    }
}
