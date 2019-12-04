<?php
declare(strict_types=1);

namespace HZEX\TpSwoole;

use HZEX\TpSwoole\Command\ServerCommand;
use InvalidArgumentException;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Swoole\Http\Server as HttpServer;
use Swoole\Server as Server;
use Swoole\WebSocket\Server as WebsocketServer;
use think\Container;
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
     * @var Logger
     */
    protected static $logger;

    /**
     * @return HttpServer|Server|WebsocketServer
     */
    public static function getServer()
    {
        return self::$server;
    }

    /**
     * 服务注册
     */
    public function register()
    {
        if (false === HZEX_SWOOLE_ENABLE) {
            return false;
        }

        $this->isWebsocket = $this->app->config->get('swoole.websocket.enabled', false);

        $this->app->bind('swoole.server', function () {
            if (null === static::$server) {
                $this->createSwooleServer();
            }
            return static::$server;
        });
        $this->app->bind('swoole.event', SwooleEvent::class);
        $this->app->bind(PidManager::class, function () {
            return new PidManager($this->app->config->get('swoole.server.options.pid_file'));
        });
        $this->app->bind('manager', Manager::class);
        $this->app->bind(Container::class, 'app');
        $this->app->bind(ContainerInterface::class, 'app');

        $this->app->bind('swoole.log', function () {
            if (null === static::$logger) {
                $this->initLogger();
            }
            return static::$logger;
        });
        return true;
    }

    /**
     * 服务启动
     */
    public function boot()
    {
        if (false === HZEX_SWOOLE_ENABLE) {
            return;
        }

        $this->commands(ServerCommand::class);
    }

    /**
     * Create swoole server.
     */
    protected function createSwooleServer()
    {
        $config = $this->app->config->get('swoole.server');

        [$host, $port] = $this->parseListen($config);

        $socketType = $config['socket_type'] ?? SWOOLE_SOCK_TCP;
        $mode       = $config['mode'] ?? SWOOLE_PROCESS;

        $server         = $this->isWebsocket ? WebsocketServer::class : HttpServer::class;
        static::$server = new $server($host, $port, $mode, $socketType);

        $options = array_merge($config['options'] ?? [], [
            'task_enable_coroutine' => true,
            'send_yield'            => true,
            'reload_async'          => true,
            'enable_coroutine'      => true,
        ]);

        static::$server->set($options);
    }

    protected function parseListen(array $config)
    {
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

        return [$host, (int) $port];
    }

    protected function initLogger()
    {
        $config = [
            // 日志保存目录
            'path'      => runtime_path('logs'),
            // 日志文件名
            'filename'  => 'server.log',
            // 最大日志文件数量
            'max_files' => 7,
        ];
        $config = array_merge($config, $this->app->config->get('swoole.log.channel', []));

        if (!empty($config['path'])
            && strrpos($config['path'], DIRECTORY_SEPARATOR) !== strlen($config['path']) - 1
        ) {
            $config['path'] .= DIRECTORY_SEPARATOR;
        }

        // 初始化日志
        $handler = new RotatingFileHandler(
            $config['path'] . $config['filename'],
            $config['max_files']
        );

        $logger = new Logger('OPS');
        $logger->pushHandler($handler);

        self::$logger = $logger;
    }
}
