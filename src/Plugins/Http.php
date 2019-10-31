<?php
/** @noinspection PhpUnusedParameterInspection */

namespace HZEX\TpSwoole\Plugins;

use Closure;
use Exception;
use HZEX\TpSwoole\Contract\ResetterInterface;
use HZEX\TpSwoole\Contract\WorkerPluginContract;
use HZEX\TpSwoole\Manager;
use HZEX\TpSwoole\Sandbox;
use RuntimeException;
use Swoole\Coroutine;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server as HttpServer;
use Swoole\Server;
use Swoole\WebSocket\Server as WsServer;
use think\App;
use think\console\Output;
use think\Cookie;
use think\exception\Handle;
use Throwable;
use unzxin\zswCore\Contract\Events\SwooleHttpInterface;
use unzxin\zswCore\Contract\EventSubscribeInterface;
use unzxin\zswCore\Event;
use function HuangZx\debug_value;

class Http implements WorkerPluginContract, SwooleHttpInterface, EventSubscribeInterface
{
    /**
     * @var ResetterInterface[]
     */
    protected $resetters = [];

    protected $manager;

    public function __construct(Manager $manager, App $app)
    {
        $this->manager = $manager;
        dump(debug_value($app));
    }

    /**
     * 插件是否就绪
     * @param Manager $manager
     * @return bool
     */
    public function isReady(Manager $manager): bool
    {
        return $manager->getSwoole() instanceof HttpServer;
    }

    /**
     * 插件准备启动
     * @param Manager $manager
     * @return bool
     */
    public function prepare(Manager $manager): bool
    {
        $event = $manager->getEvents();
        $event[] = 'Request';
        $manager->withEvents($event);
        $manager->getApp()->bind(\think\Http::class, \HZEX\TpSwoole\Tp\Http::class);
        return true;
    }

    public function subscribe(Event $event): void
    {
        // 监听公共事件
        $event->onSwooleWorkerStart(Closure::fromCallable([$this, 'onStart']));
        $event->onSwooleWorkerError(Closure::fromCallable([$this, 'onError']));
        $event->onSwooleRequest(Closure::fromCallable([$this, 'onRequest']));
    }

    /**
     * 设置当前协程正在处理Http请求
     */
    public static function setHandleHttpRequest()
    {
        Coroutine::getContext()['__http_request'] = Coroutine::getCid();
    }

    /**
     * 当前协程是否处理Http请求
     * @return bool
     */
    public static function isHandleHttpRequest()
    {
        return (Coroutine::getContext()['__http_request'] ?? 0) === Coroutine::getCid();
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
        $this->setInitialResetters();
    }

    /**
     * Initialize resetters.
     */
    protected function setInitialResetters()
    {
        $resetters = [
        ];

        $resetters = array_merge($resetters, $this->manager->getApp()->config->get('swoole.resetters', []));

        foreach ($resetters as $resetter) {
            $resetterClass = $this->manager->getApp()->make($resetter);
            if (!$resetterClass instanceof ResetterInterface) {
                throw new RuntimeException("{$resetter} must implement " . ResetterInterface::class);
            }
            $this->resetters[$resetter] = $resetterClass;
        }
    }

    /**
     * Reset Application.
     */
    public function resetApp()
    {
        $app = $this->manager->getApp()->make(App::class);
        foreach ($this->resetters as $resetter) {
            $resetter->handle($app, $app->make(Sandbox::class));
        }
    }

    /**
     * @param Request $req
     * @return \HZEX\TpSwoole\Tp\Request
     */
    protected function prepareRequest(Request $req)
    {
        $header = $req->header ?: [];
        $server = $req->server ?: [];

        foreach ($header as $key => $value) {
            $server["http_" . str_replace('-', '_', $key)] = $value;
        }

        // 重新实例化请求对象 处理swoole请求数据
        /** @var \HZEX\TpSwoole\Tp\Request $request */
        $request = $this->manager->getApp()->make('request', [], true);
        $queryStr = !empty($req->server['query_string']) ? '?' . $req->server['query_string'] : '';
        $request = $request
            ->withServer($server)
            ->withGet($req->get ?: [])
            ->withPost($req->post ?: [])
            ->withCookie($req->cookie ?: [])
            ->withInput($req->rawContent())
            ->withFiles($req->files ?: [])
            ->setBaseUrl($req->server['request_uri'])
            ->setUrl($req->server['request_uri'] . $queryStr)
            ->setPathinfo(ltrim($req->server['path_info'], '/'));

        // ->withPost($req->post ?: $request->getInputData($request->getInput()))
        // ->withPut($request->getInputData($request->getInput()));
        return $request;
    }

    /**
     * @param Response        $swResponse
     * @param \think\Response $thinkResponse
     * @param Cookie          $cookie
     */
    protected function sendResponse(Response $swResponse, \think\Response $thinkResponse, Cookie $cookie)
    {
        // 发送Header
        foreach ($thinkResponse->getHeader() as $key => $val) {
            $swResponse->header($key, $val);
        }

        // 发送状态码
        $swResponse->status($thinkResponse->getCode());

        foreach ($this->manager->getApp()->cookie->getCookie() as $name => $val) {
            list($value, $expire, $option) = $val;

            $swResponse->cookie(
                $name,
                $value,
                $expire,
                $option['path'],
                $option['domain'],
                $option['secure'] ? true : false,
                $option['httponly'] ? true : false
            );
        }

        $content = $thinkResponse->getContent();

        $swResponse->end($content);
    }

    /**
     * @param \think\Request $request
     * @return \think\Response
     */
    public function run(\think\Request $request)
    {
        /** @var \think\Http $http */
        $http = $this->manager->getApp()->make(\think\Http::class);
        $response = $http->run($request);
        $http->end($response);
        return $response;
    }

    /**
     * 请求到达回调（Http）
     * @param Request  $sRequest
     * @param Response $sResponse
     * @throws Throwable
     */
    public function onRequest(Request $sRequest, Response $sResponse): void
    {
        $this->manager->runInSandbox(function (\think\Http $http, Event $event, App $app) use ($sRequest, $sResponse) {
            self::setHandleHttpRequest();

            $request = $this->prepareRequest($sRequest);
            try {
                $response = $this->handleRequest($http, $request);
            } catch (Throwable $e) {
                $response = $this->manager->getApp()
                    ->make(Handle::class)
                    ->render($request, $e);
            }

            $this->sendResponse($sResponse, $response, $app->cookie);
        });
    }

    protected function handleRequest(\think\Http $http, $request)
    {
        $response = $http->run($request);
        $http->end($response);
        return $response;
    }

    public function requestEnd()
    {
    }

    /**
     * Log server error.
     *
     * @param Throwable|Exception $e
     */
    public function logServerError(Throwable $e)
    {
        /** @var Handle $handle */
        $handle = $this->manager->getApp()->make(Handle::class);

        $handle->renderForConsole(new Output(), $e);

        $handle->report($e);
    }
}
