<?php

namespace HZEX\TpSwoole;

use Closure;
use HZEX\TpSwoole\Concerns\InteractsWithWebsocket;
use HZEX\TpSwoole\Process\Child\FileMonitor;
use HZEX\TpSwoole\Swoole\SwooleServerHttpInterface;
use HZEX\TpSwoole\Swoole\SwooleServerInterface;
use HZEX\TpSwoole\Tp\Log\Driver\SocketLog;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server as HttpServer;
use Swoole\Server;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server as WsServer;
use think\Container;
use Throwable;

class Manager implements SwooleServerInterface, SwooleServerHttpInterface
{
    /** @var Container */
    private $container;

    /** @var HttpServer|WsServer */
    private $swoole;

    /** @var array */
    private $config;

    /** @var bool  */
    protected $isWebsocket = false;

    /** @var bool  */
    protected $handShakeHandle = false;

    /** @var array  */
    protected $websocketClients = [];

    /** @var int */
    private $pid;

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

    public function __construct()
    {
        $this->container = Container::getInstance();

        $this->swoole = $this->container->make('swoole.server');
        $this->config = $this->container->config->pull('swoole');

        $this->isWebsocket = $this->config['websocket']['enabled'] ?? false;

        if ($this->config['auto_reload'] ?? false) {
            $this->swoole->addProcess((new FileMonitor($this))->makeProcess());
        }

        $this->initialize();
    }

    protected function initialize()
    {
        $this->registerServerEvent();

        $this->container->make(Http::class)->registerHandle();
    }

    protected function registerServerEvent()
    {
        $this->swoole->on('Start', Closure::fromCallable([$this, 'onStart']));
        $this->swoole->on('ManagerStart', Closure::fromCallable([$this, 'onManagerStart']));
        $this->swoole->on('WorkerStart', Closure::fromCallable([$this, 'onWorkerStart']));
        $this->swoole->on('WorkerError', Closure::fromCallable([$this, 'onWorkerError']));

        $this->swoole->on('Task', Closure::fromCallable([$this, 'onTask']));
        $this->swoole->on('Finish', Closure::fromCallable([$this, 'onFinish']));

        $this->swoole->on('Close', Closure::fromCallable([$this, 'onClose']));

        $this->swoole->on('Request', Closure::fromCallable([$this, 'onRequest']));

        if ($this->isWebsocket) {
            $this->swoole->on('Open', Closure::fromCallable([$this, 'onOpen']));
            $this->swoole->on('Message', Closure::fromCallable([$this, 'onMessage']));
        }
        if ($this->isWebsocket && $this->handShakeHandle) {
            $this->swoole->on('HandShake', Closure::fromCallable([$this, 'onHandShake']));
        }
    }

    /**
     * 获取SwooleServer实例
     * @return WsServer
     */
    public function getSwoole()
    {
        return $this->swoole;
    }

    /**
     * 启动服务
     */
    public function start()
    {
        $this->swoole->start();
    }

    /**
     * Stop swoole server.
     */
    public function stop()
    {
        $this->swoole->shutdown();
    }

    /**
     * 主进程启动
     * @param HttpServer|Server|WsServer $server
     */
    public function onStart($server): void
    {
        swoole_set_process_name('php-ps: master');
        $this->pid = $server->master_pid;
    }

    /**
     * 主进程结束
     * @param HttpServer|WsServer $server
     */
    public function onShutdown($server): void
    {
        unlink($this->swoole->setting['pid_file']);
    }

    /**
     * 管理进程启动
     * @param HttpServer|Server|WsServer $server
     */
    public function onManagerStart($server): void
    {
        swoole_set_process_name('php-ps: manager');
    }

    /**
     * 管理进程结束
     * @param HttpServer|WsServer $server
     */
    public function onManagerStop($server): void
    {
        // TODO: Implement onManagerStop() method.
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
        $this->container->hook->listen('swoole.' . __FUNCTION__, func_get_args());
    }

    /**
     * 工作进程终止（Worker/Task）
     * @param     $server
     * @param int $workerId
     */
    public function onWorkerStop($server, int $workerId): void
    {
        // 事件触发
        $this->container->hook->listen('swoole.' . __FUNCTION__, func_get_args());
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
        $this->container->hook->listen('swoole.' . __FUNCTION__, func_get_args());
    }

    /**
     * 工作进程收到消息
     * @param HttpServer|WsServer $server
     * @param int                 $srcWorkerId
     * @param mixed               $message
     */
    public function onPipeMessage($server, int $srcWorkerId, $message): void
    {
        // TODO: Implement onPipeMessage() method.
    }

    /**
     * 请求到达回调（Http）
     * @param Request  $request
     * @param Response $response
     * @throws Throwable
     */
    public function onRequest(Request $request, Response $response): void
    {
        // 事件触发
        $this->container->hook->listen('swoole.' . __FUNCTION__, func_get_args());
    }

    /**
     * 自定义握手
     * @param Request  $request
     * @param Response $response
     */
    protected function onHandShake(Request $request, Response $response)
    {
    }

    /**
     * 连接建立回调（WebSocket）
     * @param WsServer $server
     * @param Request         $request
     */
    protected function onOpen(WsServer $server, Request $request)
    {
        $this->websocketClients[$request->fd] = true;
        echo "join#{$request->fd}\n";
    }

    /**
     * 消息到达回调（WebSocket）
     * @param WsServer $server
     * @param Frame           $frame
     */
    protected function onMessage(WsServer $server, Frame $frame)
    {
        echo "accept#{$frame->fd}: {$frame->data}\n";
        $server->push($frame->fd, "Reply: {$frame->data}");
    }

    /**
     * 连接关闭回调
     * @param Server|HttpServer|WsServer $server
     * @param int                        $fd
     * @param int                        $reactorId
     */
    protected function onClose($server, int $fd, int $reactorId)
    {
        if ($this->isWebsocket && isset($this->websocketClients[$fd])) {
            unset($this->websocketClients[$fd]);
            $this->onWsClose($server, $fd, $reactorId);
        }
    }

    /**
     * 连接关闭回调（WebSocket）
     * @param WsServer $server
     * @param int             $fd
     * @param int             $reactorId
     */
    protected function onWsClose(WsServer $server, int $fd, int $reactorId)
    {
        echo "disconnect#{$fd}: {$reactorId}\n";
    }

    /**
     * 任务处理回调
     * @param WsServer $server
     * @param int      $task_id
     * @param int      $src_worker_id
     * @param        $data
     * @return null
     */
    protected function onTask(WsServer $server, int $task_id, int $src_worker_id, $data)
    {
        // $tasl = new Task();
        $result = null;

        if (is_array($data)) {
            if (SocketLog::class === $data['action']) {
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
     * @param int      $taskId
     * @param string   $data
     */
    protected function onFinish($server, int $taskId, string $data)
    {
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
