<?php
/** @noinspection PhpUnusedParameterInspection */

namespace HZEX\TpSwoole\Worker;

use Closure;
use Exception;
use HZEX\TpSwoole\Manager;
use HZEX\TpSwoole\Resetters\ResetHttp;
use HZEX\TpSwoole\Resetters\ResetterContract;
use HZEX\TpSwoole\Resetters\ResetValidate;
use RuntimeException;
use Swoole\Coroutine;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server as HttpServer;
use Swoole\Server;
use Swoole\WebSocket\Server as WsServer;
use think\App;
use think\console\Output;
use think\exception\Handle;
use Throwable;
use unzxin\zswCore\Contract\Events\SwooleHttpInterface;
use unzxin\zswCore\Contract\EventSubscribeInterface;
use unzxin\zswCore\Event;

class Http implements WorkerPluginContract, SwooleHttpInterface, EventSubscribeInterface
{
    /**
     * @var ResetterContract[]
     */
    protected $resetters = [];

    public function __construct()
    {
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

        // 预加载
        $this->getApp()->make('db');
        return true;
    }

    public function subscribe(Event $event): void
    {
        // 监听公共事件
        $event->onSwooleWorkerStart(Closure::fromCallable([$this, 'onStart']));
        $event->onSwooleWorkerError(Closure::fromCallable([$this, 'onError']));
        $event->onSwooleRequest(Closure::fromCallable([$this, 'onRequest']));
    }

    public function getApp(): App
    {
        return App::getInstance();
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
            // ClearInstances::class,
            ResetHttp::class,
            ResetValidate::class,
        ];

        $resetters = array_merge($resetters, $this->getApp()->config->get('swoole.resetters', []));

        foreach ($resetters as $resetter) {
            $resetterClass = $this->getApp()->make($resetter);
            if (!$resetterClass instanceof ResetterContract) {
                throw new RuntimeException("{$resetter} must implement " . ResetterContract::class);
            }
            $this->resetters[$resetter] = $resetterClass;
        }
    }

    /**
     * Reset Application.
     */
    public function resetApp()
    {
        $app = $this->getApp()->make(App::class);
        foreach ($this->resetters as $resetter) {
            $resetter->handle($app);
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

        // 暂时性兼容request类型限制的解决方案
        if (isset($server['server_port'])) {
            $server['server_port'] = (string) $server['server_port'];
        }
        if (isset($server['remote_port'])) {
            $server['remote_port'] = (string) $server['remote_port'];
        }

        foreach ($header as $key => $value) {
            $server["http_" . str_replace('-', '_', $key)] = $value;
        }

        // 重新实例化请求对象 处理swoole请求数据
        /** @var \HZEX\TpSwoole\Tp\Request $request */
        $request = $this->getApp()->request;
        $queryStr = !empty($req->server['query_string']) ? '?' . $req->server['query_string'] : '';
        $request = $request
            ->withHeader($header)
            ->withServer($server)
            ->withGet($req->get ?: [])
            ->withCookie($req->cookie ?: [])
            ->withInput($req->rawContent())
            ->withFiles($req->files ?: [])
            ->setBaseUrl($req->server['request_uri'])
            ->setUrl($req->server['request_uri'] . $queryStr)
            ->setPathinfo(ltrim($req->server['path_info'], '/'))
            ->withPost($req->post ?: $request->getInputData($request->getInput()))
            ->withPut($request->getInputData($request->getInput()));

        return $request;
    }

    /**
     * @param \think\Response $thinkResponse
     * @param Response        $swooleResponse
     */
    protected function sendResponse(\think\Response $thinkResponse, Response $swooleResponse)
    {
        // 获取数据
        $data = $thinkResponse->getContent();

        // 发送Header
        foreach ($thinkResponse->getHeader() as $key => $val) {
            $swooleResponse->header($key, $val);
        }

        // 发送状态码
        $swooleResponse->status($thinkResponse->getCode());

        foreach ($this->getApp()->cookie->getCookie() as $name => $val) {
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

        $swooleResponse->end($data);
    }

    /**
     * @param \think\Request $request
     * @return \think\Response
     */
    public function run(\think\Request $request)
    {
        /** @var \think\Http $http */
        $http = $this->getApp()->make(\think\Http::class);
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
        try {
            self::setHandleHttpRequest();
            $request = $this->prepareRequest($sRequest);
            $this->resetApp();
            $response = $this->run($request);
            $this->sendResponse($response, $sResponse);
            // 请求完成
            $this->requestEnd();
        } catch (Throwable $e) {
            if (isset($request)) {
                try {
                    /** @var Handle $handle */
                    $handle = $this->getApp()->make(Handle::class);
                    $exceptionResponse = $handle->render($request, $e);

                    $this->sendResponse($exceptionResponse, $sResponse);
                } catch (Throwable $e) {
                    $this->logServerError($e);
                }
            } else {
                $this->logServerError($e);
            }
        }
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
        $handle = $this->getApp()->make(Handle::class);

        $handle->renderForConsole(new Output(), $e);

        $handle->report($e);
    }
}
