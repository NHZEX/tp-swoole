<?php

namespace HZEX\TpSwoole;

use Closure;
use Exception;
use HZEX\TpSwoole\Facade\Server as ServerFacade;
use HZEX\TpSwoole\Process\Child\FileMonitor;
use HZEX\TpSwoole\Swoole\SwooleServerHttpInterface;
use HZEX\TpSwoole\Swoole\SwooleServerInterface;
use HZEX\TpSwoole\Tp\Log\Driver\SocketLog;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server as HttpServer;
use Swoole\Http2\Request as H2Request;
use Swoole\Http2\Response as H2Response;
use Swoole\Server;
use Swoole\WebSocket\Server as WsServer;
use think\App;
use think\console\Output as ConsoleOutput;
use think\Container;
use think\Error;
use Throwable;

class Manager implements SwooleServerInterface, SwooleServerHttpInterface
{
    use Concerns\MessageSwitchTrait;

    /** @var App 服务绑定在App上，不要使用容器替代App */
    private $app;

    /** @var Event */
    private $event;

    /** @var HttpServer|WsServer */
    private $swoole;

    /** @var array */
    private $config;

    /** @var */
    private $workerId;

    /** @var array 支持的响应事件 */
    protected $events = [
        'Start', 'Shutdown', // Server
        'ManagerStart', 'ManagerStop', // Manager
        'WorkerStart', 'WorkerStop', 'WorkerExit', 'WorkerError', // Worker
        'PipeMessage', // Message
        'Task', 'Finish', // Task
        'Connect', 'Receive', 'Close', // Tcp
        'Packet', // Udp
        'Request', // Http
        'HandShake', 'Open', 'Message' // WebSocket
    ];

    /**
     * Manager constructor.
     * @param App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->event = $this->app->make(Event::class);

        $this->swoole = ServerFacade::instance();
        $this->config = $this->app->config->pull('swoole');

        if ($this->config['auto_reload'] ?? false) {
            $this->initChildProcess[] = (new FileMonitor());
        }

        $this->initialize();
    }

    protected function initialize()
    {
        $this->initMessageSwitch();
        $this->registerServerEvent();
        $this->mountProcess();

        /** @var Http $http */
        $http = $this->app->make(Http::class);
        $http->registerEvent();

        if ($this->swoole instanceof WsServer) {
            /** @var WebSocket $websocket */
            $websocket = $this->app->make(WebSocket::class);
            $websocket->setHandler($this->config['websocket']['handler'])->registerEvent();
        }

        \HZEX\TpSwoole\Facade\Event::subscribe([
            Http::class,
            WebSocket::class,
        ]);
    }

    protected function registerServerEvent()
    {
        // Http
        if ($this->swoole instanceof HttpServer) {
            $this->events[] = 'Request';
        }
        // WebSocket
        if ($this->swoole instanceof WsServer) {
            // $this->events[] = 'HandShake';
            $this->events[] = 'Open';
            $this->events[] = 'Message';
        }

        foreach ($this->events as $event) {
            $listener = "on$event";
            $callback = method_exists($this, $listener)
                ? Closure::fromCallable([$this, $listener])
                : function () use ($listener) {
                    $this->event->trigger("swoole.$listener", func_get_args());
                };
            $this->swoole->on($event, $callback);
        }
    }

    /**
     * 获取SwooleServer实例
     * @return Server|HttpServer|WsServer
     */
    public function getSwoole()
    {
        static $swoole;
        if (null === $swoole) {
            $swoole = ServerFacade::instance();
        }
        return $swoole;
    }

    /**
     * @return Event
     */
    public function getEvent(): Event
    {
        return $this->event;
    }

    /**
     * 启动服务
     */
    public function start()
    {
        ServerFacade::instance()->start();
    }

    /**
     * Stop swoole server.
     */
    public function stop()
    {
        ServerFacade::instance()->shutdown();
    }

    /**
     * 主进程启动
     * @param HttpServer|Server|WsServer $server
     */
    public function onStart($server): void
    {
        swoole_set_process_name('php-ps: master');
        // 事件触发
        $this->event->trigger('swoole.' . __FUNCTION__, func_get_args());
    }

    /**
     * 主进程结束
     * @param HttpServer|WsServer $server
     */
    public function onShutdown($server): void
    {
        unlink($this->swoole->setting['pid_file']);
        // 事件触发
        $this->event->trigger('swoole.' . __FUNCTION__, func_get_args());
    }

    /**
     * 管理进程启动
     * @param HttpServer|Server|WsServer $server
     */
    public function onManagerStart($server): void
    {
        swoole_set_process_name('php-ps: manager');
        // 事件触发
        $this->event->trigger('swoole.' . __FUNCTION__, func_get_args());
    }

    /**
     * 管理进程结束
     * @param HttpServer|WsServer $server
     */
    public function onManagerStop($server): void
    {
        // 事件触发
        $this->event->trigger('swoole.' . __FUNCTION__, func_get_args());
    }

    /**
     * 工作进程启动（Worker/Task）
     * @param HttpServer|Server|WsServer $server
     * @param int                        $workerId
     */
    public function onWorkerStart($server, int $workerId): void
    {
        $type = $server->taskworker ? 'task' : 'worker';
        swoole_set_process_name("php-ps: {$type}#{$workerId}");
        // 设置当前工人Id
        $this->workerId = $workerId;
        // 事件触发
        $this->event->trigger('swoole.' . __FUNCTION__, func_get_args());
    }

    /**
     * 工作进程终止（Worker/Task）
     * @param     $server
     * @param int $workerId
     */
    public function onWorkerStop($server, int $workerId): void
    {
        // 事件触发
        $this->event->trigger('swoole.' . __FUNCTION__, func_get_args());
    }

    /**
     * 工作进程异常（Worker/Task）
     * @param HttpServer|Server|WsServer $server
     * @param int                        $workerId
     * @param int                        $workerPid
     * @param int                        $exitCode
     * @param int                        $signal
     */
    public function onWorkerError($server, int $workerId, int $workerPid, int $exitCode, int $signal): void
    {
        echo "WorkerError: $workerId, pid: $workerPid, execCode: $exitCode, signal: $signal\n";
        // 事件触发
        $this->event->trigger('swoole.' . __FUNCTION__, func_get_args());
    }

    /**
     * 工作进程收到消息
     * @param HttpServer|WsServer $server
     * @param int                 $srcWorkerId
     * @param mixed               $message
     */
    public function onPipeMessage($server, int $srcWorkerId, $message): void
    {
        // 事件触发
        $this->event->trigger('swoole.' . __FUNCTION__, func_get_args());
    }

    /**
     * 连接关闭回调（Tcp）
     * @param Server|HttpServer|WsServer $server
     * @param int                        $fd
     * @param int                        $reactorId
     */
    protected function onClose($server, int $fd, int $reactorId)
    {
        // 事件触发
        $this->event->trigger('swoole.' . __FUNCTION__, func_get_args());
    }

    /**
     * 请求到达回调（Http）
     * @param Request|H2Request   $request
     * @param Response|H2Response $response
     */
    public function onRequest(Request $request, Response $response): void
    {
        // 事件触发
        $this->event->trigger('swoole.' . __FUNCTION__, func_get_args());
    }

    /**
     * 任务处理回调
     * @param WsServer $server
     * @param int      $task_id
     * @param int      $src_worker_id
     * @param          $data
     * @return null
     */
    protected function onTask(WsServer $server, int $task_id, int $src_worker_id, $data)
    {
        // TODO 等待新版
        // $tasl = new Task();
        $result = null;

        if (is_array($data)) {
            if (SocketLog::class === $data['action']) {
                // TODO 等待 swoole 修复发布
//                $chan = new Coroutine\Channel(1);
//                go(function () use ($data, $chan) {
//                    $cli = new Client($data['host'], $data['port']);
//                    $cli->setMethod('POST');
//                    $cli->setHeaders([
//                        'Host' => $data['host'],
//                        'Content-Type' => 'application/json;charset=UTF-8',
//                        'Accept' => 'text/html,application/xhtml+xml,application/xml',
//                        'Accept-Encoding' => 'gzip',
//                    ]);
//                    $cli->set(['timeout' => 3, 'keep_alive' => true]);
//                    $cli->post($data['address'], $data['message']);
//                    $chan->push($cli->statusCode);
//                });
//                $result = $chan->pop();
            }
        }
        //完成任务，结束并返回数据
        // $task->finish($result);
        return $result;
    }

    /**
     * 任务完成响应
     * @param HttpServer|WsServer $server
     * @param int                 $taskId
     * @param string              $data
     */
    protected function onFinish($server, int $taskId, string $data)
    {
        // 未触发事件
    }

    /**
     * Log server error.
     *
     * @param Throwable|Exception $e
     */
    public static function logServerError(Throwable $e)
    {
        if ($e instanceof \Error || $e instanceof Throwable) {
            echo $e->__toString();
            return;
        }
        try {
            Error::getExceptionHandler()->renderForConsole(new ConsoleOutput, $e);
        } catch (Throwable $exception) {
            echo $exception->__toString();
        }
    }

    /**
     * 调试容器
     * @param Container $container
     * @param           $workerId
     */
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
}
