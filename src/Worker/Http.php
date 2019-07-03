<?php
/** @noinspection PhpUnusedParameterInspection */

namespace HZEX\TpSwoole\Worker;

use Closure;
use Exception;
use HZEX\TpSwoole\Event;
use HZEX\TpSwoole\EventSubscribeInterface;
use HZEX\TpSwoole\Manager;
use HZEX\TpSwoole\Resetters2\RebindHttpContainer;
use HZEX\TpSwoole\Resetters2\RebindRouterContainer;
use HZEX\TpSwoole\Resetters2\RebindValidate;
use HZEX\TpSwoole\Resetters2\RebindViewContainer;
use HZEX\TpSwoole\Resetters2\ResetApp;
use HZEX\TpSwoole\Resetters2\ResetMiddleware;
use HZEX\TpSwoole\Resetters2\ResetterContract;
use HZEX\TpSwoole\Swoole\SwooleServerHttpInterface;
use RuntimeException;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server as HttpServer;
use Swoole\Server;
use Swoole\WebSocket\Server as WsServer;
use think\App;
use think\console\Output;
use think\exception\Handle;
use Throwable;

class Http implements WorkerPluginContract, SwooleServerHttpInterface, EventSubscribeInterface
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
        return true;
    }

    public function subscribe(Event $event): void
    {
        // 监听公共事件
        $event->listen('swoole.onWorkerStart', Closure::fromCallable([$this, 'onStart']));
        $event->listen('swoole.onWorkerError', Closure::fromCallable([$this, 'onError']));
        $event->listen('swoole.onRequest', Closure::fromCallable([$this, 'onRequest']));
    }

    public function getApp(): App
    {
        return App::getInstance();
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
            ResetApp::class,
            ResetMiddleware::class,
            RebindHttpContainer::class,
            RebindRouterContainer::class,
            RebindViewContainer::class,
            RebindValidate::class,
            // BindRequest::class,
            // ResetConfig::class,
            // ResetEvent::class,
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

    protected function prepareRequest(Request $req)
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

        // 重新实例化请求对象 处理swoole请求数据
        /** @var \HZEX\TpSwoole\Tp\Request $request */
        $request = $this->getApp()->request;
        $queryStr = !empty($req->server['query_string']) ? '&' . $req->server['query_string'] : '';
        $request = $request
            ->withHeader($header)
            ->withServer($server)
            ->withGet($req->get ?: [])
            ->withCookie($req->cookie ?: [])
            ->withInput($req->rawContent())
            ->withFiles($req->files ?: [])
            ->setBaseUrl($req->server['request_uri'])
            ->setUrl($req->server['request_uri'] . $queryStr)
            ->setPathinfo(ltrim($req->server['path_info'], '/'));

        $request->withPost($req->post ?: $request->getInputData($request->getInput()));
        $request->withPut($request->getInputData($request->getInput()));

        // 覆盖内置请求实例命名
        $this->getApp()->instance(\think\Request::class, $request);
        return $request;
    }

    protected function sendResponse(\think\Response $thinkResponse, Response $swooleResponse)
    {

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

        $content = $thinkResponse->getContent();

        if (!empty($content)) {
            $swooleResponse->write($content);
        }

        $swooleResponse->end();
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
        $response->getContent();
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
        } /*finally {
            // $sandbox->clear();
        }*/
    }

    public function requestEnd()
    {
        $this->getApp()->log->save();
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
