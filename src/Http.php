<?php

namespace HZEX\TpSwoole;

use Exception;
use HZEX\TpSwoole\Swoole\SwooleServerHttpInterface;
use HZEX\TpSwoole\Tp\App;
use HZEX\TpSwoole\Tp\Cookie;
use HZEX\TpSwoole\Tp\Log;
use HZEX\TpSwoole\Tp\Session;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server as HttpServer;
use Swoole\Server;
use Swoole\WebSocket\Server as WsServer;
use think\console\Output as ConsoleOutput;
use think\Error;
use think\Facade;
use think\facade\Cookie as CookieFacade;
use think\facade\Session as SessionFacade;
use Throwable;

class Http implements SwooleServerHttpInterface
{
    /** @var App */
    protected $app;
    /** @var int */
    protected $workerId;

    public function __construct()
    {
        $this->app = App::getInstance();
    }

    public function registerHandle()
    {
        $this->app->hook->add('swoole.onWorkerStart', function ($p) {$this->onWorkerStart(...$p);});
        $this->app->hook->add('swoole.onWorkerError', function ($p) {$this->onWorkerError(...$p);});
        $this->app->hook->add('swoole.onRequest', function ($p) {$this->onRequest(...$p);});
    }

    /**
     * @return int
     */
    public function getWorkerId()
    {
        return $this->workerId;
    }

    /**
     *
     */
    private function initApp()
    {
        // 应用实例化
        $this->app = new App($this->app->getAppPath());
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
    protected function onWorkerStart($server, int $workerId): void
    {
        if (false === $server->taskworker) {
            $this->workerId = $workerId;
            $this->initApp();
        }
    }

    /**
     * 工作进程异常（Worker/Task）
     * @param HttpServer $server
     * @param int        $workerId
     * @param int        $workerPid
     * @param int        $exitCode
     * @param int        $signal
     */
    protected function onWorkerError(HttpServer $server, int $workerId, int $workerPid, int $exitCode, int $signal): void
    {
        echo "WorkerError: $workerId, pid: $workerPid, execCode: $exitCode, signal: $signal\n";
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
            $this->logServerError($e);
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
     * @param App             $app
     * @param \think\Response $thinkResponse
     * @param Response        $swooleResponse
     */
    protected function sendResponse(App $app, \think\Response $thinkResponse, Response $swooleResponse)
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
    }

    public function appShutdown()
    {
        $this->app->log->save();
    }

    /**
     * Log server error.
     *
     * @param Throwable|Exception $e
     */
    public function logServerError(Throwable $e)
    {
        Error::getExceptionHandler()->renderForConsole(new ConsoleOutput, $e);
    }
}
