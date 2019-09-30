<?php

namespace HZEX\TpSwoole;

use Closure;
use Exception;
use HZEX\TpSwoole\Facade\Server as ServerFacade;
use HZEX\TpSwoole\Log\MonologErrorHandler;
use HZEX\TpSwoole\Process\FileWatch;
use HZEX\TpSwoole\Task\SocketLogTask;
use HZEX\TpSwoole\Task\TaskInterface;
use HZEX\TpSwoole\Worker\ConnectionPool;
use HZEX\TpSwoole\Worker\Http;
use HZEX\TpSwoole\Worker\WebSocket;
use HZEX\TpSwoole\Worker\WorkerPluginContract;
use Psr\Log\LoggerInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server as HttpServer;
use Swoole\Http2\Request as H2Request;
use Swoole\Http2\Response as H2Response;
use Swoole\Process;
use Swoole\Runtime;
use Swoole\Server;
use Swoole\Timer;
use Swoole\WebSocket\Server as WsServer;
use think\App;
use think\console\Output;
use unzxin\zswCore\Contract\Events\SwooleHttpInterface;
use unzxin\zswCore\Contract\Events\SwoolePipeMessageInterface;
use unzxin\zswCore\Contract\Events\SwooleServerInterface;
use unzxin\zswCore\Contract\Events\SwooleServerTaskInterface;
use unzxin\zswCore\Contract\Events\SwooleWorkerInterface;
use unzxin\zswCore\Contract\EventSubscribeInterface;
use unzxin\zswCore\Event;
use unzxin\zswCore\ProcessPool;

class Manager implements
    SwooleServerInterface,
    SwooleWorkerInterface,
    SwooleHttpInterface,
    SwooleServerTaskInterface,
    SwoolePipeMessageInterface
{
    /**
     * @var string
     */
    private $instanceId;

    /**
     * @var Output
     */
    private $output;

    /**
     * @var App $app
     */
    private $app;

    /**
     * @var Server|HttpServer|WsServer $swoole
     */
    private $swoole;

    /**
     * @var array
     */
    private $config;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var MonologErrorHandler
     */
    private $exceptionRecord;

    /**
     * @var array
     */
    private $tasks = [
        SocketLogTask::class
    ];

    /**
     * 插件
     * @var array
     */
    private $plugins = [
        ConnectionPool::class,
        Http::class,
        WebSocket::class,
    ];

    /**
     * @var array $subscribes
     */
    private $subscribes = [];

    /**
     * 进程池
     * @var ProcessPool
     */
    private $processPool;

    /** @var array 支持的响应事件 */
    protected $events = [
        'Start', 'Shutdown', // Server
        'ManagerStart', 'ManagerStop', // Manager
        'WorkerStart', 'WorkerStop', 'WorkerExit', 'WorkerError', // Worker
        'PipeMessage', // Message
        'Task', 'Finish', // Task
        'Connect', 'Receive', 'Close', // Tcp
        'Packet', // Udp
    ];

    /**
     * Manager constructor.
     * @param App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;

        $this->instanceId = crc32(spl_object_hash($this));
        $this->swoole = ServerFacade::instance();
        $this->config = $this->app->config->get('swoole');
        $this->subscribes = $this->config['events'] ?? [];
        $this->tasks = array_merge($this->tasks, $this->config['tasks'] ?? []);
        $this->plugins = array_merge($this->plugins, $this->config['plugins'] ?? []);

        // 设置运行时内存限制
        ini_set('memory_limit', $this->config['memory_limit'] ?: '512M');

        $this->processPool = ProcessPool::makeServer($this->swoole);
    }

    /**
     * @throws Exception
     */
    public function initialize()
    {
        // 加载虚拟容器配置
        VirtualContainer::loadConfiguration();
        // 初始化插件
        $this->initPlugins();
        // 注册任务处理
        $this->registerTasks();
        // 注册系统事件触发
        $this->registerEvent();
        // 注册应用事件监听
        $this->initSubscribe();
        // 初始外部进程集
        $this->initProcess();
    }

    /**
     * 初始化插件
     * @throws Exception
     */
    protected function initPlugins()
    {
        $subscribe = [];
        foreach ($this->plugins as $plugin) {
            if (is_string($plugin)) {
                /** @var WorkerPluginContract $plugin */
                $plugin = $this->app->make($plugin);
            }
            $this->logger->info('init plugins: ' . get_class($plugin));
            if (false === $plugin instanceof WorkerPluginContract) {
                throw new Exception('无效插件: ' . get_class($plugin));
            }
            // 如果插件未准备就绪就跳过
            if (false === $plugin->isReady($this)) {
                continue;
            }
            // 如果实现事件订阅则添加到待订阅列表
            if ($plugin instanceof EventSubscribeInterface) {
                $subscribe[] = $plugin;
            }
            // 执行启动准备过程
            $plugin->prepare($this);
        }
        $this->subscribes = array_merge($subscribe, $this->subscribes);
    }

    /**
     * @throws Exception
     */
    protected function registerTasks()
    {
        foreach ($this->tasks as $task) {
            if (!is_subclass_of($task, TaskInterface::class)) {
                throw new Exception('无效任务: ' . $task);
            }
        }
    }

    /**
     * 注册系统事件触发
     */
    protected function registerEvent()
    {
        foreach ($this->events as $event) {
            $listener = "on$event";
            $callback = method_exists($this, $listener)
                ? Closure::fromCallable([$this, $listener])
                : function () use ($event) {
                    $this->getEvent()->triggerSwoole($event, func_get_args());
                };
            $this->swoole->on($event, $callback);
        }
    }

    /**
     * 注册应用事件监听
     */
    protected function initSubscribe()
    {
        $this->getEvent()->subscribe($this->subscribes);
    }

    /**
     * 初始外部进程集
     * @throws Exception
     */
    protected function initProcess()
    {
        $this->processPool->setLogger($this->logger);
        if ($this->config['hot_reload']['enable'] ?? false) {
            /** @var FileWatch $fw */
            $fw = $this->app->make(FileWatch::class);
            $fw->setConfig($this->config['hot_reload']);
            $fw->setManager($this);
            $this->processPool->add($fw);
        }
        foreach ($this->config['process'] ?? [] as $process) {
            $this->processPool->add($this->app->make($process));
        }
        $this->processPool->start();

    }

    /**
     * @return string
     */
    public function getInstanceId(): string
    {
        return $this->instanceId;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;

        $this->exceptionRecord = new MonologErrorHandler($logger);
        $this->exceptionRecord->registerExceptionHandler();
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @param Output $output
     * @return void
     */
    public function setOutput(Output $output)
    {
        $this->output = $output;
        return;
    }

    /**
     * @return Output
     */
    public function getOutput(): Output
    {
        return $this->output;
    }

    /**
     * @return MonologErrorHandler
     */
    public function getExceptionRecord(): MonologErrorHandler
    {
        return $this->exceptionRecord;
    }

    /**
     * @return array
     */
    public function getEvents()
    {
        return $this->events;
    }

    /**
     * 更改监听事件
     * @param array $events
     * @return $this
     */
    public function withEvents(array $events)
    {
        $this->events = $events;
        return $this;
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
        return $this->app->make(App::class)->make(Event::class);
    }

    /**
     * 启动服务
     */
    public function start()
    {
        Runtime::enableCoroutine($this->config['enable_coroutine'] ?? false);
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
        // 输出调试信息
        $this->logger->info("master start\t#{$server->master_pid}");
        // 设置进程名称
        swoole_set_process_name('php-ps: master');
        // 响应终端 ctrl+c
        Process::signal(SIGINT, function () use ($server) {
            echo PHP_EOL;
            $server->shutdown();
        });
        // 事件触发
        $this->getEvent()->trigSwooleStart(func_get_args());
    }

    /**
     * 主进程结束
     * @param HttpServer|WsServer $server
     */
    public function onShutdown($server): void
    {
        // 输出调试信息
        $this->logger->info("master shutdown\t#{$server->master_pid}");
        // 事件触发
        $this->getEvent()->trigSwooleShutdown(func_get_args());
    }

    /**
     * 管理进程启动
     * @param HttpServer|Server|WsServer $server
     */
    public function onManagerStart($server): void
    {
        // 输出调试信息
        $this->logger->info("manager start\t#{$server->manager_pid}");
        // 设置进程名称
        swoole_set_process_name('php-ps: manager');
        // 事件触发
        $this->getEvent()->trigSwooleManagerStart(func_get_args());
    }

    /**
     * 管理进程结束
     * @param HttpServer|WsServer $server
     */
    public function onManagerStop($server): void
    {
        // 输出调试信息
        $this->logger->info("manager stop\t#{$server->manager_pid}");
        // 事件触发
        $this->getEvent()->trigSwooleManagerStop(func_get_args());
    }

    /**
     * 工作进程启动（Worker/Task）
     * @param HttpServer|Server|WsServer $server
     * @param int                        $workerId
     */
    public function onWorkerStart($server, int $workerId): void
    {
        $type = $server->taskworker ? 'task' : 'worker';
        // 输出调试信息
        $this->logger->info("{$type} start\t#{$workerId}({$server->worker_pid})");
        // 设置进程名称
        swoole_set_process_name("php-ps: {$type}#{$workerId}");
        // 事件触发
        $this->getEvent()->trigSwooleWorkerStart(func_get_args());
    }

    /**
     * 工作进程终止（Worker/Task）
     * @param     $server
     * @param int $workerId
     */
    public function onWorkerStop($server, int $workerId): void
    {
        $type = $server->taskworker ? 'task' : 'worker';
        $this->logger->info("{$type} stop\t#{$workerId}({$server->worker_pid})");
        // 事件触发
        $this->getEvent()->trigSwooleWorkerStop(func_get_args());
    }

    /**
     * 工作进程退出（Worker/Task）
     * @param     $server
     * @param int $workerId
     */
    public function onWorkerExit($server, int $workerId): void
    {
        $type = $server->taskworker ? 'task' : 'worker';
        $this->logger->info("{$type} exit\t#{$workerId}({$server->worker_pid})");
        // 事件触发
        $this->getEvent()->trigSwooleWorkerExit(func_get_args());
        // 清理全部定时器
        Timer::clearAll();
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
        $this->logger->error("WorkerError: $workerId, pid: $workerPid, execCode: $exitCode, signal: $signal");
        // 事件触发
        $this->getEvent()->trigSwooleWorkerError(func_get_args());
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
        $this->getEvent()->trigSwoolePipeMessage(func_get_args());
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
        $this->getEvent()->trigSwooleClose(func_get_args());
    }

    /**
     * 请求到达回调（Http）
     * @param Request|H2Request   $request
     * @param Response|H2Response $response
     */
    public function onRequest(Request $request, Response $response): void
    {
        // 事件触发
        $this->getEvent()->trigSwooleRequest(func_get_args());
    }

    /**
     * 任务处理回调
     * @param Server      $server
     * @param Server\Task $task
     */
    public function onTask($server, Server\Task $task)
    {
        if (!is_array($task->data)
            || count($task->data) !== 2
            || !in_array($task->data[0], $this->tasks, true)
        ) {
            $this->getEvent()->trigSwooleTask(func_get_args());
        }
        [$action, $data] = $task->data;
        /** @var TaskInterface $taskHandle */
        $taskHandle = new $action($server, $task);
        $result = $taskHandle->handle($data);
        //完成任务，结束并返回数据
        $task->finish($result);
    }

    /**
     * 任务完成响应
     * @param HttpServer|WsServer $server
     * @param int                 $taskId
     * @param string              $data
     */
    public function onFinish($server, int $taskId, $data): void
    {
        // 未触发事件
        $this->getEvent()->trigSwooleFinish(func_get_args());
    }
}
