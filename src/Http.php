<?php

namespace HZEX\TpSwoole;

use Closure;
use HZEX\TpSwoole\Swoole\SwooleServerHttpInterface;
use HZEX\TpSwoole\Tp\Cookie;
use HZEX\TpSwoole\Tp\Log;
use HZEX\TpSwoole\Tp\Session;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server as HttpServer;
use Swoole\Server;
use Swoole\WebSocket\Server as WsServer;
use think\App;
use think\Facade;
use think\facade\Cookie as CookieFacade;
use think\facade\Session as SessionFacade;
use Throwable;

class Http implements SwooleServerHttpInterface
{
    /** @var Tp\App|App */
    protected $app;
    /** @var bool */
    private $isRegistered = false;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function registerEvent()
    {
        if ($this->isRegistered) {
            return;
        }
        $this->isRegistered = true;

        // 监听公共事件
        $this->app->event->listen('swoole.onWorkerStart', Closure::fromCallable([$this, 'onStart']));
        $this->app->event->listen('swoole.onWorkerError', Closure::fromCallable([$this, 'onError']));

        // 监听私有事件
        /** @var WsServer $swoole */
        $swoole = \HZEX\TpSwoole\Facade\Server::instance();
        $swoole->on('Request', Closure::fromCallable([$this, 'onRequest']));
    }

    /**
     *
     */
    private function initApp()
    {
        // 应用实例化
        $this->app = new Tp\App();
        // 重新绑定日志类
        $this->app->bindTo('log', Log::class);

        // 绑定中间件文件
        $this->app->bindTo('middleware.global.file', function () {
            if (is_file($this->app->getAppPath() . 'middleware.php')) {
                /** @noinspection PhpIncludeInspection */
                $middleware = include $this->app->getAppPath() . 'middleware.php';
                if (is_array($middleware)) {
                    return $middleware;
                }
            }
            return [];
        });
        // 绑定门面
        Facade::bind([
            CookieFacade::class => Cookie::class,
            SessionFacade::class => Session::class,
        ]);
        // 应用初始化
        $this->app->initialize();
        // 覆盖绑定
        $this->app->bindTo([
            'log' => Log::class,
            'cookie' => Cookie::class,
            'session' => Session::class,
        ]);
    }

    /**
     * 工作进程启动（Worker/Task）
     * @param HttpServer|Server|WsServer $server
     * @param int                        $workerId
     */
    protected function onStart($server, int $workerId): void
    {
        if ($server->taskworker) {
            return;
        }
        $this->initApp();
    }

    /**
     * 工作进程异常（Worker/Task）
     * @param HttpServer $server
     * @param int        $workerId
     * @param int        $workerPid
     * @param int        $exitCode
     * @param int        $signal
     */
    protected function onError(HttpServer $server, int $workerId, int $workerPid, int $exitCode, int $signal): void
    {
        $this->appShutdown();
    }

    /**
     * 请求到达回调（Http）
     * @param Request  $request
     * @param Response $response
     * @throws Throwable
     */
    public function onRequest(Request $request, Response $response): void
    {
        try {
            // 执行应用并响应
            $resp = $this->app->runSwoole($request);
            // 发送请求
            $this->sendResponse($this->app, $resp, $response);
            // 请求完成
            $this->appShutdown();
        } catch (Throwable $e) {
            Manager::logServerError($e);
        } finally {
            $this->clear($this->app);
        }
    }

    /**
     * @param App $app
     */
    protected function clear(App $app)
    {
        // 清理对象实例
        $instances = $app->config->get('swoole.instances', []);
        $instances[] = \think\Request::class;
        $instances[] = Cookie::class;
        $instances[] = Session::class;
        foreach ($instances as $instance) {
            $app->delete($instance);
        }
        // 清除中间件数据
        $app->middleware->clear();
    }

    /**
     * 发送数据
     * @param Tp\App          $app
     * @param \think\Response $thinkResponse
     * @param Response        $swooleResponse
     * @return bool
     */
    protected function sendResponse(Tp\App $app, \think\Response $thinkResponse, Response $swooleResponse)
    {
        // 发送Header
        foreach ($thinkResponse->getHeader() as $key => $val) {
            $swooleResponse->header($key, $val);
        }

        // 发送Cookie
        foreach ($app->cookie->getCookie() as $name => $val) {
            list($value, $expire, $option) = $val;

            $swooleResponse->cookie($name, $value, $expire, $option['path'], $option['domain'], $option['secure'] ? true : false, $option['httponly'] ? true : false);
        }

        // 发送状态码
        $swooleResponse->status($thinkResponse->getCode());

        $content = $thinkResponse->getContent();

        if (!empty($content)) {
            $swooleResponse->write($content);
        }

        $swooleResponse->end();

        // 中止事件
        return true;
    }

    public function appShutdown()
    {
        $this->app->log->save();
    }
}
