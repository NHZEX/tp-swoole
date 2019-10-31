<?php

namespace HZEX\TpSwoole;

use Closure;
use Exception;
use HZEX\TpSwoole\Concerns\InteractsWithHttp;
use HZEX\TpSwoole\Concerns\InteractsWithServer;
use HZEX\TpSwoole\Concerns\InteractsWithTask;
use HZEX\TpSwoole\Concerns\InteractsWithWorker;
use HZEX\TpSwoole\Contract\WorkerPluginContract;
use HZEX\TpSwoole\Facade\Server as ServerFacade;
use HZEX\TpSwoole\Log\MonologConsoleHandler;
use HZEX\TpSwoole\Log\MonologErrorHandler;
use HZEX\TpSwoole\Plugins\ConnectionPool;
use HZEX\TpSwoole\Plugins\Http;
use HZEX\TpSwoole\Plugins\WebSocket;
use HZEX\TpSwoole\Process\FileWatch;
use HZEX\TpSwoole\Task\SocketLogTask;
use HZEX\TpSwoole\Task\TaskInterface;
use HZEX\TpSwoole\Tp\Pool\Cache;
use HZEX\TpSwoole\Tp\Pool\Db;
use HZEX\TpSwoole\Tp\Request;
use HZEX\TpSwoole\VirtualContainer as SwooleApp;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Swoole\Http\Server as HttpServer;
use Swoole\Process;
use Swoole\Server;
use Swoole\WebSocket\Server as WsServer;
use think\App;
use think\console\Output;
use think\Container;
use unzxin\zswCore\Contract\Events\SwooleHttpInterface;
use unzxin\zswCore\Contract\Events\SwoolePipeMessageInterface;
use unzxin\zswCore\Contract\Events\SwooleServerInterface;
use unzxin\zswCore\Contract\Events\SwooleServerTaskInterface;
use unzxin\zswCore\Contract\Events\SwooleWorkerInterface;
use unzxin\zswCore\Contract\EventSubscribeInterface;
use unzxin\zswCore\Event;
use unzxin\zswCore\ProcessPool;
use function HuangZx\debug_value;

class Manager implements
    SwooleServerInterface,
    SwooleWorkerInterface,
    SwooleHttpInterface,
    SwooleServerTaskInterface,
    SwoolePipeMessageInterface
{
    use InteractsWithServer;
    use InteractsWithWorker;
    use InteractsWithHttp;
    use InteractsWithTask;

    /**
     * @var string
     */
    private $instanceId;

    /**
     * @var Output
     */
    private $output;

    /**
     * @var App
     */
    protected $container;

    /**
     * @var SwooleApp $app
     */
    private $app;

    /** @var PidManager */
    private $pidManager;

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
     * @var Sandbox
     */
    private $sandbox;

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
     * @param App        $container
     * @param PidManager $pidManager
     */
    public function __construct(App $container, PidManager $pidManager)
    {
        $this->container  = $container;
        $this->pidManager = $pidManager;

        $this->instanceId = crc32(spl_object_hash($this));
        $this->swoole = ServerFacade::instance();
        $this->config = $container->config->get('swoole');
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
        // 准备应用
        $this->prepareApplication();
        // 加载沙箱
        $this->sandbox = $this->app->make(Sandbox::class);
        // $this->app = $this->sandbox->getBaseApp();
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
     * 准备应用
     */
    protected function prepareApplication()
    {
        if (!$this->app instanceof SwooleApp) {
            $this->app = new SwooleApp($this->container->getRootPath());
            $this->app->bind(SwooleApp::class, App::class);
            $this->app->bind(ContainerInterface::class, Container::class);
            $this->app->bind('request', Request::class);
            $this->app->bind(\think\Http::class, Tp\Http::class);
            $this->app->instance(Manager::class, $this);
            $this->app->instance('swoole.server', $this->container->make('swoole.server'));
            $this->app->instance(PidManager::class, $this->container->make(PidManager::class));
            // 基础基础实例
            $this->getEvent()->setResolver(new EventResolver());
            // 绑定连接池
            if ($this->app->config->get('swoole.pool.db.enable', true)) {
                $this->app->delete('db');
                $this->app->bind('db', Db::class);
            }
            if ($this->app->config->get('pool.cache.enable', true)) {
                $this->app->delete('cache');
                $this->app->bind('cache', Cache::class);
            }
            $this->app->initialize();
            // 预加载
            $this->prepareConcretes();
            dump(debug_value((array) $this->container->getIterator()));
            dump(debug_value((array) $this->app->getIterator()));
        }
    }

    /**
     * 预加载
     */
    protected function prepareConcretes()
    {
        $defaultConcretes = ['db', 'cache', 'event'];

        foreach ($defaultConcretes as $concrete) {
            if ($this->app->exists($concrete)) {
                $this->app->make($concrete);
            }
        }
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
     * @return Sandbox
     */
    public function getSandbox(): Sandbox
    {
        return $this->sandbox;
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
        return $this->swoole;
    }

    /**
     * @return Event
     */
    public function getEvent(): Event
    {
        return $this->app->make(Event::class);
    }

    /**
     * @return PidManager
     */
    public function getPidManager(): PidManager
    {
        return $this->pidManager;
    }

    /**
     * 启动服务
     */
    public function start()
    {
        MonologConsoleHandler::setDaemon($this->config['server']['options']['daemonize'] ?? false);
        ServerFacade::instance()->start();
    }

    /**
     * 停止服务
     */
    public function stop()
    {
        ServerFacade::instance()->shutdown();
    }

    /**
     * 重载服务
     */
    public function reload()
    {
        if (Process::kill($this->swoole->manager_pid, 0)) {
            $this->swoole->reload();
        }
    }
}
