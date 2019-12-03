<?php

namespace HZEX\TpSwoole;

use HZEX\TpSwoole\Container\ClearLogDestroy;
use HZEX\TpSwoole\Contract\ContractDestroyInterface;
use HZEX\TpSwoole\Contract\ResetterInterface;
use HZEX\TpSwoole\Coroutine\Context;
use HZEX\TpSwoole\Coroutine\ContextDestroy;
use HZEX\TpSwoole\Plugins\ConnectionPool;
use HZEX\TpSwoole\Resetters\ClearInstances;
use HZEX\TpSwoole\Resetters\ResetApp;
use HZEX\TpSwoole\Resetters\ResetDb;
use HZEX\TpSwoole\Resetters\ResetEvent;
use HZEX\TpSwoole\Resetters\ResetService;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Swoole\Coroutine;
use think\App;
use think\Config;
use think\Container;
use think\Event;
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
    private $shared = [
        ContainerInterface::class,
        Container::class,
        App::class,
        VirtualContainer::class,
        Sandbox::class,
        SwooleEvent::class,
        Manager::class,
        ConnectionPool::class,
        'app',
        'swoole.server',
        'db',
        'cache',
    ];

    /**
     * 直传实例
     * @var array
     */
    protected $resolveShared = [];

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
    public function setBaseApp(Container $app): self
    {
        $this->app = $app;

        return $this;
    }

    /**
     * @return App 获取基本容器
     */
    public function getBaseApp(): App
    {
        return $this->app;
    }

    /**
     * 初始化沙箱
     * @return $this
     */
    protected function initialize(): self
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
    protected function setInitialCleans(): void
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
    protected function setDirectInstances(): void
    {
        $this->shared = array_merge($this->shared, $this->config->get('swoole.container.shared', []));
        $app          = $this->getBaseApp();
        foreach ($this->shared as $penetrate) {
            $this->resolveShared[$app->getAlias($penetrate)] = true;
        }
    }

    /**
     * 添加直通实例
     * @param $class
     */
    public function addDirectInstances(string $class): void
    {
        $this->resolveShared[$this->getBaseApp()->getAlias($class)] = true;
    }

    /**
     * 获取容器
     * @return App|null
     */
    public function getApplication(): ?App
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
        $this->setContext($snapshot);
        $this->resetApp($snapshot);

        return $snapshot;
    }

    protected function mirrorInstances(Container $snapshot): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $instancesRef = ref_get_prop($snapshot, 'instances');
        /** @var array $instances */
        $instances = $instancesRef->getValue();
        foreach ($instances as $class => $object) {
            if (isset($this->resolveShared[$class]) || isset($this->resolveShared[get_class($object)])) {
                $instances[$class] = $object;
            } else {
                $instances[$class] = clone $object;
            }
        }
        $instancesRef->setValue($instances);

        $this->setInstance($snapshot);
    }

    protected function setContext(Container $snapshot): void
    {
        Context::setData('__app', $snapshot);
        $destroy = new ContextDestroy($snapshot, $this->containerDestroy);
        defer(function () use ($destroy) {
            $destroy->__destruct();
        });
    }

    /**
     * 获取容器快照
     * Get current snapshot.
     * @return App|null
     */
    public function getSnapshot(): ?App
    {
        return Context::getData('__app', null);
    }

    /**
     * 重设容器实例
     * @param Container $app
     */
    public function setInstance(Container $app): void
    {
        $app->instance('app', $app);
        $app->instance(App::class, $app);
        $app->instance(Container::class, $app);
        $app->instance(ContainerInterface::class, $app);
    }

    /**
     * 拷贝 Config 副本
     * Set initial config.
     */
    protected function setInitialConfig(): void
    {
        $this->config = clone $this->getBaseApp()->config;
    }

    /**
     * 拷贝 Event 副本
     */
    protected function setInitialEvent(): void
    {
        $this->event = clone $this->getBaseApp()->event;
    }

    /**
     * 获取 Config
     * Get config snapshot.
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * 获取 Event
     * @return Event
     */
    public function getEvent(): Event
    {
        return $this->event;
    }

    /**
     * 获取服务重设器
     * @return array
     */
    public function getServices(): array
    {
        return $this->services;
    }

    /**
     * 初始化服务重设器
     */
    protected function setInitialServices(): void
    {
        $app = $this->getBaseApp();

        $services = $this->config->get('swoole.services', []);

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
    protected function setInitialResetters(): void
    {
        $app = $this->getBaseApp();

        $resetters = [
            ClearInstances::class,
            ResetApp::class,
            // ResetConfig::class,
            ResetEvent::class,
            ResetService::class,
            ResetDb::class,
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
    protected function resetApp(Container $app): void
    {
        foreach ($this->resetters as $resetter) {
            $resetter->handle($app, $this);
        }
    }

}
