<?php

namespace HZEX\TpSwoole;

use HZEX\TpSwoole\Tp\App;
use HZEX\TpSwoole\Tp\Cookie;
use HZEX\TpSwoole\Tp\Log;
use HZEX\TpSwoole\Tp\Session;
use Swoole\Coroutine;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Server;
use think\Container;
use think\Exception;
use think\Facade;
use think\facade\Cookie as CookieFacade;
use think\facade\Session as SessionFacade;
use Throwable;

class Http
{
    /** @var App */
    protected $app;
    /** @var Server */
    protected $server;
    /** @var int */
    protected $workerId;

    public function __construct(Server $server, int $workerId)
    {
        $this->server = $server;
        $this->workerId = $workerId;

        $this->initApp();
    }

    private function initApp()
    {
        // 应用实例化
        $this->app = new App(App::getInstance()->getAppPath());
        // 重新绑定日志类
        $this->app->bindTo('log', Log::class);
        // 绑定swoole实例
        $this->app->bindTo('swoole.server', $this->server);
        $this->app->bindTo('swoole.worker.id', function () {
            return $this->workerId;
        });
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
     * @param Request $req
     * @param App     $app
     * @return \think\Request
     */
    protected function prepareRequest(Request $req, App $app)
    {
        $header = $req->header ?: [];
        $server = $req->server ?: [];

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
            $server['path_info'] = $req->get[$app->config->get('var_pathinfo')];
        }

        /** @var \think\Request $request */
        $request = $this->app->make('request', [], true);

        return $request->withHeader($header)
            ->withServer($server)
            ->withGet($req->get ?: [])
            ->withPost($req->post ?: [])
            ->withCookie($req->cookie ?: [])
            ->withInput($req->rawContent())
            ->withFiles($req->files ?: [])
            ->setBaseUrl($req->server['request_uri'])
            ->setUrl($req->server['request_uri'] . (!empty($req->server['query_string']) ? '&' . $req->server['query_string'] : ''))
            ->setPathinfo(ltrim($req->server['path_info'], '/'));
    }

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

    /**
     * @param Request  $request
     * @param Response $response
     * @throws Throwable
     */
    public function httpRequest(Request $request, Response $response)
    {
        $this->app->log->debug("workerId: {$this->workerId}, coroutineId: " . Coroutine::getCid());

        // 准备请求对象
        //$this->prepareRequest($request, $this->app);

        // 执行应用并响应
        $resp = $this->app->swoole($request, $response);
        //$resp = $this->app->runSwoole();

        // 发送请求
        $this->sendResponse($this->app, $resp, $response);

        // 清理对象实例
        $this->app->delete(\think\Request::class);
        $this->app->delete(Cookie::class);
        $this->app->delete(Session::class);
        // 清除中间件数据
        $this->app->middleware->clear();
    }

    public static function debugContainer(Container $container, $workerId)
    {
        $debug = array_map(function ($val) use ($workerId) {
            if (is_object($val)) {
                return get_class($val) . ' = ' . hash('crc32', spl_object_hash($val) . $workerId);
            } else {
                return $val;
            }
        }, $container->all());
        ksort($debug, SORT_STRING); // SORT_FLAG_CASE
        $container->log->debug($debug);
    }

    public function appShutdown()
    {
        $this->app->log->save();
    }
}
