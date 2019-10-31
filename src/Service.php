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
        $this->app->bind(PidManager::class, function () {
            return new PidManager($this->app->config->get('swoole.server.options.pid_file'));
        });
        $this->app->bind(ContainerInterface::class, Container::class);
        $this->app->bind('manager', Manager::class);

        $this->initLogger();
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
        $mode       = $config['mode'] ?? SWOOLE_PROCESS;

        $server         = $this->isWebsocket ? WebsocketServer::class : HttpServer::class;
        static::$server = new $server($host, (int) $port, $mode, $socketType);

        static::$server->set($config['options'] ?? []);
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

        $this->app->instance(Logger::class, $logger);
        $this->app->bind('monolog', Logger::class);
    }
}
