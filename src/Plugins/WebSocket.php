<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Plugins;

use Closure;
use HZEX\TpSwoole\Contract\WorkerPluginContract;
use HZEX\TpSwoole\Log\MonologErrorHandler;
use HZEX\TpSwoole\Manager;
use HZEX\TpSwoole\WebSocket\HandlerContract;
use HZEX\TpSwoole\WebSocket\HandShakeContract;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server as WsServer;
use think\App;
use think\Config;
use Throwable;
use unzxin\zswCore\Contract\Events\SwooleWebSocketInterface;
use unzxin\zswCore\Contract\EventSubscribeInterface;
use unzxin\zswCore\Event;

class WebSocket implements WorkerPluginContract, SwooleWebSocketInterface, EventSubscribeInterface
{
    /** @var App */
    private $app;
    /** @var array */
    private $config = [
        'enabled' => true,
        // 'host' => '0.0.0.0', // 监听地址
        // 'port' => 9502, // 监听端口
        // 'sock_type' => SWOOLE_TCP, // sock type 默认为SWOOLE_SOCK_TCP
        'handler' => '',
        // 'parser' => Parser::class,
        // 'route_file' => base_path() . 'websocket.php',
        'ping_interval' => 25000,
        'ping_timeout' => 60000,
    ];
    /** @var HandlerContract|HandShakeContract */
    private $handle;
    /** @var bool */
    private $handShakeHandle = false;
    /** @var WsServer */
    private $server;
    /**
     * @var MonologErrorHandler
     */
    private $exceptionRecord;

    public function __construct(App $app, Config $config)
    {
        $this->app = $app;
        $this->config = $config->get('swoole.websocket', []) + $this->config;
        // 加载处理器
        $this->setHandler($this->config['handler']);
    }

    /**
     * 插件是否就绪
     * @param Manager $manager
     * @return bool
     */
    public function isReady(Manager $manager): bool
    {
        return $manager->getSwoole() instanceof WsServer;
    }

    /**
     * 插件准备启动
     * @param Manager $manager
     * @return bool
     */
    public function prepare(Manager $manager): bool
    {
        $this->server = $manager->getSwoole();
        $event = $manager->getEvents();
        $this->handShakeHandle && $event[] = 'HandShake';
        $event[] = 'Open';
        $event[] = 'Message';
        $manager->withEvents($event);
        $this->exceptionRecord = $manager->getExceptionRecord();
        return true;
    }

    public function subscribe(Event $event): void
    {
        $event->onSwooleWorkerStart(Closure::fromCallable([$this, 'onStart']));
        $event->onSwooleWorkerStop(Closure::fromCallable([$this, 'onStop']));
        $event->onSwooleOpen(Closure::fromCallable([$this, 'onOpen']));
        $event->onSwooleMessage(Closure::fromCallable([$this, 'onMessage']));
        $event->onSwooleClose(function (WsServer $server, $fd, int $reactorId): void {
            if ($server->isEstablished($fd)) {
                $this->onClose($server, $fd, $reactorId);
            }
        });
        if ($this->handShakeHandle) {
            $event->onSwooleHandShake(Closure::fromCallable([$this, 'onHandShake']));
        }
    }

    protected function setHandler(string $class)
    {
        if (class_exists($class)) {
            $this->handle = $this->app->make($class);
            if (false === $this->handle instanceof HandlerContract) {
                $this->handle = null;
            }
            if ($this->handle instanceof HandShakeContract) {
                $this->handShakeHandle = true;
            }
        }
        return $this;
    }

    /**
     * 连接建立回调（WebSocket）
     * @param WsServer $server
     * @param int      $workerId
     */
    public function onStart($server, int $workerId): void
    {
        $this->handle->onStart($server, $workerId);
    }

    /**
     * 工作进程终止（Worker/Task）
     * @param WsServer $server
     * @param int      $workerId
     */
    public function onStop($server, int $workerId): void
    {
        $this->handle->onStop($server, $workerId);
    }

    /**
     * 连接握手回调（WebSocket）
     * @param Request  $request
     * @param Response $response
     */
    public function onHandShake(Request $request, Response $response): void
    {
        if ($this->handle->onHandShake($request, $response)) {
            $this->server->defer(function () use ($request) {
                $this->onOpen($this->server, $request);
            });
        }
    }

    /**
     * 连接建立回调（WebSocket）
     * @param WsServer $server
     * @param Request  $request
     */
    public function onOpen(WsServer $server, Request $request): void
    {
        $this->handle->onOpen($server, $request);
    }

    /**
     * 消息到达回调（WebSocket）
     * @param WsServer $server
     * @param Frame    $frame
     */
    public function onMessage(WsServer $server, Frame $frame): void
    {
        try {
            $this->handle->onMessage($server, $frame);
        } catch (Throwable $e) {
            $this->exceptionRecord->handleException($e);
        }
    }

    /**
     * 连接关闭回调（WebSocket）
     * @param WsServer        $server
     * @param                 $fd
     * @param int             $reactorId
     */
    public function onClose(WsServer $server, $fd, int $reactorId): void
    {
        $this->handle->onClose($server, $fd, $reactorId);
    }
}
