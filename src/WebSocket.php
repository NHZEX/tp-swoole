<?php
declare(strict_types=1);

namespace HZEX\TpSwoole;

use Closure;
use HZEX\TpSwoole\Facade\Server;
use HZEX\TpSwoole\Swoole\SwooleWebSocketInterface;
use HZEX\TpSwoole\WebSocket\HandlerContract;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server as WsServer;
use think\App;
use Throwable;

class WebSocket implements SwooleWebSocketInterface
{
    /** @var App */
    private $app;
    /** @var HandlerContract */
    private $handle;
    /** @var bool */
    private $isRegistered = false;
    /** @var bool */
    private $handShakeHandle = false;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function setHandler(string $class)
    {
        if (class_exists($class)) {
            $this->handle = $this->app->make($class);
            if (false === $this->handle instanceof HandlerContract) {
                $this->handle = null;
            }
        }
        return $this;
    }

    public function registerEvent()
    {
        if ($this->isRegistered) {
            return;
        }
        if (empty($this->handle)) {
            return;
        }

        $this->isRegistered = true;

        // 监听公共事件
        $event = $this->app->make(Event::class);
        $event->listen('swoole.onClose', function (WsServer $server, $fd, int $reactorId): void
        {
            if ($server->isEstablished($fd)) {
                $this->onClose($server, $fd, $reactorId);
            }
        });

        // 监听私有事件
        /** @var WsServer $swoole */
        $swoole = Server::instance();
        $swoole->on('Open', Closure::fromCallable([$this, 'onOpen']));
        $swoole->on('Message', Closure::fromCallable([$this, 'onMessage']));
        if ($this->handShakeHandle) {
            $swoole->on('HandShake', Closure::fromCallable([$this, 'onHandShake']));
        }
    }

    /**
     * 连接握手回调（WebSocket）
     * @param Request  $request
     * @param Response $response
     */
    public function onHandShake(Request $request, Response $response): void
    {
        // TODO: Implement onHandShake() method.
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
            Manager::logServerError($e);
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
