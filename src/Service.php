<?php
declare(strict_types=1);

namespace HZEX\TpSwoole;

use HZEX\TpSwoole\Command\ServerCommand;
use HZEX\TpSwoole\Facade\Server as ServerFacade;
use Swoole\Http\Server as HttpServer;
use Swoole\Runtime;
use Swoole\WebSocket\Server as WebsocketServer;
use think\App;
use think\Console;

class Service
{
    /** @var App  */
    protected $app;
    /** @var bool  */
    protected $isWebsocket = false;
    /** @var HttpServer|WebsocketServer */
    protected static $server;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function register()
    {
        $this->isWebsocket = $this->app->config->get('swoole.websocket.enabled', false);
        Runtime::enableCoroutine($this->app->config->get('swoole.enable_coroutine', false));

        $this->app->bindTo('swoole.server', function () {
            if (is_null(static::$server)) {
                $this->createSwooleServer();
            }

            return static::$server;
        });
        // $this->app->bindTo('swoole.server', ServerFacade::class);
        $this->app->bindTo('manager', Manager::class);

        $this->commands(ServerCommand::class);
    }

    /**
     * Create swoole server.
     */
    protected function createSwooleServer()
    {
        $server     = $this->isWebsocket ? WebsocketServer::class : HttpServer::class;
        $config     = $this->app->config;
        $host       = $config->get('swoole.server.host');
        $port       = $config->get('swoole.server.port');
        $socketType = $config->get('swoole.server.socket_type', SWOOLE_SOCK_TCP);
        $mode       = $config->get('swoole.server.mode', SWOOLE_PROCESS);

        static::$server = new $server($host, $port, $mode, $socketType);

        $options = $config->get('swoole.server.options');

        static::$server->set($options);
    }

    /**
     * 添加指令
     * @access protected
     * @param array|string $commands 指令
     */
    protected function commands($commands)
    {
        $commands = is_array($commands) ? $commands : func_get_args();

        Console::addDefaultCommands($commands);
    }
}
