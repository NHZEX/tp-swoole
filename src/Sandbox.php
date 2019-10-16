<?php

namespace HZEX\TpSwoole;

use HZEX\TpSwoole\Container\ClearLogDestroy;
use HZEX\TpSwoole\Contract\ContractDestroyInterface;
use HZEX\TpSwoole\Contract\ResetterInterface;
use HZEX\TpSwoole\Coroutine\CoConstruct;
use HZEX\TpSwoole\Coroutine\CoDestroy;
use HZEX\TpSwoole\Resetters\ClearInstances;
use HZEX\TpSwoole\Resetters\ResetApp;
use HZEX\TpSwoole\Resetters\ResetEvent;
use HZEX\TpSwoole\Resetters\ResetService;
use HZEX\TpSwoole\Tp\Pool\Cache;
use HZEX\TpSwoole\Tp\Pool\Db;
use HZEX\TpSwoole\Worker\ConnectionPool;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionObject;
use RuntimeException;
use Swoole\Coroutine;
use think\App;
use think\Config;
use think\Console;
use think\Container;
use think\Env;
use think\Event;
use think\service\PaginatorService;
use unzxin\zswCore\Event as SwooleEvent;
use function HuangZx\ref_get_prop;

class Sandbox
{
    /** @var Manager */
    protected $manager;

    /** @var App */
    protected $app;

    /** @var Config */
    protected $config;

    /** @var Event */
    protected $event;

    /** @var ResetterInterface[] */
    protected $resetters = [];
    protected $services  = [];

    protected $containerDestroy = [];

    /**
     * 不拷贝容器镜像的实例
     * @var array
     */
    private $penetrates = [
        ContainerInterface::class,
        Container::class,
        App::class,
        VirtualContainer::class,
        Sandbox::class,
        Config::class,
        Console::class,
        Env::class,
        SwooleEvent::class,
        Manager::class,
        ConnectionPool::class,
        'swoole.server',
        Db::class,
        Cache::class,
    ];

    /**
     * 直传实例
     * @var array
     */
    protected $direct = [];

    /**
     * 实例化沙箱
     * Sandbox constructor.
     * @param Container $app
     * @param Manager   $manager
     */
    public function __construct(Container $app, Manager $manager)
    {
        $this->manager = $manager;
        $this->setBaseApp($app);

        $this->initialize();
    }

    /**
     * 设置基本容器
     * @param Container $app
     * @return $this
     */
    public function setBaseApp(Container $app)
    {
        $this->app = $app;

        return $this;
    }

    /**
     * @return App 获取基本容器
     */
    public function getBaseApp()
    {
        return $this->app;
    }

    /**
     * 初始化沙箱
     * @return $this
     */
    protected function initialize()
    {
        $this->setInitialConfig();
        $this->setInitialServices();
        $this->setInitialEvent();
        $this->setInitialResetters();
        $this->setInitialCleans();
        $this->setDirectInstances();

        Container::setInstance(function () {
            return $this->getApplication();
        });

        return $this;
    }

    /**
     * 初始化清理器
     */
    protected function setInitialCleans()
    {
        $destroys = $this->config->get('swoole.container.destroy', []);
        $defaultDestroys = [
            ClearLogDestroy::class,
        ];

        $destroys = array_merge($defaultDestroys, $destroys);

        foreach ($destroys as $destroy) {
            $destroyClass = $this->app->make($destroy);
            if (!$destroyClass instanceof ContractDestroyInterface) {
                throw new RuntimeException("{$destroy} must implement " . ContractDestroyInterface::class);
            }
            $this->containerDestroy[$destroy] = $destroyClass;
        }
    }

    /**
     * 初始化直传实例
     */
    protected function setDirectInstances()
    {
        $penetrates = $this->config->get('swoole.penetrates', []);
        $this->penetrates = array_merge($this->penetrates, $penetrates);
        $app = $this->getBaseApp();
        foreach ($this->penetrates as $penetrate) {
            $this->direct[$app->getAlias($penetrate)] = true;
        }
    }

    protected function setIniVirtualContainer()
    {
        $refVc = new ReflectionClass(VirtualContainer::class);
        /** @var VirtualContainer $vc */
        $vc = $refVc->newInstanceWithoutConstructor();
        $refVc = new ReflectionObject($vc);
        $baseApp = $this->getBaseApp();
        $refApp = new ReflectionObject($baseApp);
        foreach ($refApp->getProperties() as $property) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $refVcp = $refVc->getProperty($property->getName());
            $refVcp->setAccessible(true);
            $property->setAccessible(true);
            $refVcp->setValue($vc, $property->getValue($baseApp));
        }
        $this->setInstance($vc);
        $this->setBaseApp($vc);
    }

    /**
     * 获取容器
     * @return App|null
     */
    public function getApplication()
    {
        if (-1 === Coroutine::getCid()) {
            return $this->getBaseApp();
        }
        $snapshot = $this->getSnapshot();
        if ($snapshot instanceof Container) {
            return $snapshot;
        }
        $snapshot = clone $this->getBaseApp();
        $this->mirrorInstances($snapshot);
        $this->resetApp($snapshot);
        $this->setContext($snapshot);

        return $snapshot;
    }

    protected function mirrorInstances(Container $snapshot)
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $instancesRef = ref_get_prop($snapshot, 'instances');
        /** @var array $instances */
        $instances = $instancesRef->getValue();
        foreach ($instances as $class => $object) {
            if (isset($this->direct[$class]) || isset($this->direct[get_class($object)])) {
                $instances[$class] = $object;
            } else {
                $instances[$class] = clone $object;
            }
        }
        $instancesRef->setValue($instances);

        $this->setInstance($snapshot);
    }

    protected function setContext(Container $snapshot)
    {
        $cxt = Coroutine::getContext();
        $cxt['__construct'] = new CoConstruct();
        $cxt['__app'] = $snapshot;
        $cxt['__destroy'] = new CoDestroy($snapshot, $this->containerDestroy);
    }

    /**
     * 获取容器快照
     * Get current snapshot.
     * @return App|null
     */
    public function getSnapshot()
    {
        $cxt = Coroutine::getContext();
        return $cxt['__app'] ?? null;
    }

    /**
     * 重设容器实例
     * @param Container $app
     */
    public function setInstance(Container $app)
    {
        $app->instance('app', $app);
        $app->instance(Container::class, $app);
    }

    /**
     * 拷贝 Config 副本
     * Set initial config.
     */
    protected function setInitialConfig()
    {
        $this->config = clone $this->getBaseApp()->config;
    }

    /**
     * 拷贝 Event 副本
     */
    protected function setInitialEvent()
    {
        $this->event = clone $this->getBaseApp()->event;
    }

    /**
     * 获取 Config
     * Get config snapshot.
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * 获取 Event
     * @return Event
     */
    public function getEvent()
    {
        return $this->event;
    }

    /**
     * 获取服务重设器
     * @return array
     */
    public function getServices()
    {
        return $this->services;
    }

    /**
     * 初始化服务重设器
     */
    protected function setInitialServices()
    {
        $app = $this->getBaseApp();

        $services = [
            PaginatorService::class,
        ];

        $services = array_merge($services, $this->config->get('swoole.services', []));

        foreach ($services as $service) {
            if (class_exists($service) && !in_array($service, $this->services)) {
                $serviceObj               = new $service($app);
                $this->services[$service] = $serviceObj;
            }
        }
    }

    /**
     * 设置通用重设器
     * Initialize resetters.
     */
    protected function setInitialResetters()
    {
        $app = $this->getBaseApp();

        $resetters = [
            ClearInstances::class,
            ResetApp::class,
            // ResetConfig::class,
            ResetEvent::class,
            ResetService::class,
        ];

        $resetters = array_merge($resetters, $this->config->get('swoole.resetters', []));

        foreach ($resetters as $resetter) {
            $resetterClass = $app->make($resetter);
            if (!$resetterClass instanceof ResetterInterface) {
                throw new RuntimeException("{$resetter} must implement " . ResetterInterface::class);
            }
            $this->resetters[$resetter] = $resetterClass;
        }
    }

    /**
     * Reset Application.
     *
     * @param Container|App $app
     */
    protected function resetApp(Container $app)
    {
        foreach ($this->resetters as $resetter) {
            $resetter->handle($app, $this);
        }
    }

}
