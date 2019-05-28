<?php
/** @noinspection PhpUnusedParameterInspection */

namespace HZEX\TpSwoole;

use Closure;
use Exception;
use HZEX\TpSwoole\Swoole\SwooleServerHttpInterface;
use HZEX\TpSwoole\Tp\Cookie;
use HZEX\TpSwoole\Tp\Log;
use HZEX\TpSwoole\Tp\Session;
use ReflectionException;
use ReflectionObject;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server as HttpServer;
use Swoole\Server;
use Swoole\WebSocket\Server as WsServer;
use think\App;
use think\Db;
use think\Error;
use think\exception\HttpException;
use think\Facade;
use think\facade\Cookie as CookieFacade;
use think\facade\Session as SessionFacade;
use Throwable;

class Http implements SwooleServerHttpInterface, EventSubscribeInterface
{
    /**
     * @var App
     */
    protected $app;
    /**
     * @var bool
     */
    private $isRegistered = false;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function subscribe(Event $event): void
    {
        // 监听公共事件
        $event->listen('swoole.onWorkerStart', Closure::fromCallable([$this, 'onStart']));
        $event->listen('swoole.onWorkerError', Closure::fromCallable([$this, 'onError']));
        $event->listen('swoole.onRequest', Closure::fromCallable([$this, 'onRequest']));
    }

    public function registerEvent()
    {
        if ($this->isRegistered) {
            return;
        }
        $this->isRegistered = true;
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
        $this->requestEnd();
    }

    /**
     *
     */
    private function initApp()
    {
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
        // 设置新日志实例
        $newLog = $this->app->make(Log::class);
        $this->app->instance(Log::class, $newLog);
        $this->app->instance(\think\Log::class, $newLog);
        // 覆盖绑定
        $this->app->bindTo([
            'log' => Log::class,
            'cookie' => Cookie::class,
            'session' => Session::class,
        ]);
        // 应用初始化
        $this->app->initialize();
    }

    /**
     * App 值重设
     * @param App $app
     */
    public function resetFixes(App $app)
    {
        // 重置应用的开始时间和内存占用
        try {
            $ref = new ReflectionObject($app);
            $refBeginTime = $ref->getProperty('beginTime');
            $refBeginTime->setAccessible(true);
            $refBeginTime->setValue($app, microtime(true));
            $refBeginMem = $ref->getProperty('beginMem');
            $refBeginMem->setAccessible(true);
            $refBeginMem->setValue($app, memory_get_usage());
        } catch (ReflectionException $e) {
        }

        // 重置数据库查询次数
        Db::$queryTimes = 0;
        // 重置数据库执行次数
        Db::$executeTimes = 0;
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
            $this->resetFixes($this->app);
            // 执行应用并响应
            $resp = $this->run($this->app, $request);
            // 发送请求
            $this->sendResponse($this->app, $resp, $response);
        } catch (Throwable $e) {
            Manager::logServerError($e);
        } finally {
            // 请求完成
            $this->requestEnd();
            // 执行清理
            $this->clear($this->app);
        }
    }

    protected function run(App $app, Request $request): \think\Response
    {
        try {
            $header  = $request->header ?: [];
            $server  = $request->server ?: [];

            if (isset($header['x-requested-with'])) {
                $server['HTTP_X_REQUESTED_WITH'] = $header['x-requested-with'];
            }
            if (isset($header['referer'])) {
                $server['http_referer'] = $header['referer'];
            }
            if (isset($header['host'])) {
                $server['http_host'] = $header['host'];
            }
            if (isset($req->get[$app->config->get('var_pathinfo')])) {
                $server['path_info'] = $request->get[$app->config->get('var_pathinfo')];
            }

            // 重新实例化请求对象 处理swoole请求数据
            $queryStr = !empty($request->server['query_string']) ? ('&' . $request->server['query_string']) : '';
            $app->request->withHeader($header)
                ->withServer($server)
                ->withGet($request->get ?: [])
                ->withPost($request->post ?: [])
                ->withCookie($request->cookie ?: [])
                ->withInput($request->rawContent())
                ->withFiles($request->files ?: [])
                ->setBaseUrl($request->server['request_uri'])
                ->setUrl($request->server['request_uri'] . $queryStr)
                ->setHost($request->header['host'])
                ->setPathinfo(ltrim($request->server['path_info'], '/'));

            // 更新请求对象实例
            $app->route->setRequest($app->request);

            // 重新加载全局中间件
            $app->middleware->import((array) $app->make('middleware.global.file'));

            ob_start();
            $resp = $app->run();
            $resp->send();
            $content = ob_get_clean();

            // Trace调试注入
            if ($app->env->get('app_trace', $app->config->get('app_trace'))) {
                $app->debug->inject($resp, $content);
            }

            $resp->content($content);
        } catch (HttpException $e) {
            $resp = $this->exception($e);
        } catch (Exception $e) {
            $resp = $this->exception($e);
        }

        return $resp;
    }

    /**
     * @param Exception $e
     * @return \think\Response
     */
    protected function exception(Exception $e)
    {
        $handler = Error::getExceptionHandler();
        $handler->report($e);
        return $handler->render($e);
    }

    /**
     * @param App $app
     */
    protected function clear(App $app)
    {
        // 清理对象实例
        $instances = $app->config->get('swoole.instances', []);
        $instances[] = \think\Request::class;
        $instances[] = \think\Response::class;
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
     * @return bool
     */
    protected function sendResponse(App $app, \think\Response $thinkResponse, Response $swooleResponse)
    {
        // 发送Header
        foreach ($thinkResponse->getHeader() as $key => $val) {
            $swooleResponse->header($key, $val);
        }

        // 发送Cookie
        /** @var Cookie $cookie */
        $cookie = $app->cookie;
        foreach ($cookie->getCookie() as $name => $val) {
            list($value, $expire, $option) = $val;

            $swooleResponse->cookie(
                $name,
                $value,
                $expire,
                $option['path'],
                $option['domain'],
                $option['secure'] ? true : false,
                $option['httponly'] ? true : false
            );
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

    public function requestEnd()
    {
        $this->app->log->save();
    }
}
