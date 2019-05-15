<?php

namespace HZEX\TpSwoole;

use HZEX\TpSwoole\Tp\App;
use HZEX\TpSwoole\Tp\Cookie;
use HZEX\TpSwoole\Tp\Log;
use HZEX\TpSwoole\Tp\Session;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Server;
use think\Container;
use think\Facade;
use think\facade\Cookie as CookieFacade;
use think\facade\Session as SessionFacade;
use Throwable;

class Http
{
    /** @var App */
    protected $app;
    /** @var string */
    protected $appPath;

    public function __construct(string $path)
    {
        $this->appPath = $path;
    }

    /**
     * 启动
     * @param Server $server
     * @param int     $workerId
     */
    public function httpStart(Server $server, int $workerId)
    {
        // 应用实例化
        $this->app = new App($this->appPath);
        // 重新绑定日志类
        $this->app->bindTo('log', Log::class);

        //swoole server worker启动行为
        $hook = Container::get('hook');
        $hook->listen('swoole_worker_start', ['server' => $server, 'worker_id' => $workerId]);

        // Swoole Server保存到容器
        $this->app->bindTo('swoole', $server);

        Facade::bind([
            CookieFacade::class => Cookie::class,
            SessionFacade::class => Session::class,
        ]);

        // 应用初始化
        $this->app->initialize();

        $this->app->bindTo([
            'log' => Log::class,
            'cookie' => Cookie::class,
            'session' => Session::class,
        ]);
    }

    /**
     * @param Request  $request
     * @param Response $response
     * @throws Throwable
     */
    public function httpRequest(Request $request, Response $response)
    {
        // 执行应用并响应
        $this->app->swoole($request, $response);
    }

    public function appShutdown()
    {
        $this->app->log->save();
    }
}
