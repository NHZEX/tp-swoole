<?php

namespace HZEX\TpSwoole;

use Closure;
use HZEX\TpSwoole\Container\ClearLogDestroy;
use HZEX\TpSwoole\Contract\ContractDestroyInterface;
use HZEX\TpSwoole\Contract\ResetterInterface;
use HZEX\TpSwoole\Coroutine\Context;
use HZEX\TpSwoole\Coroutine\ContextDestroy;
use HZEX\TpSwoole\Resetters\ClearInstances;
use HZEX\TpSwoole\Resetters\ResetApp;
use HZEX\TpSwoole\Resetters\ResetConfig;
use HZEX\TpSwoole\Resetters\ResetEvent;
use HZEX\TpSwoole\Resetters\ResetService;
use Psr\Container\ContainerInterface;
use RuntimeException;
use think\App;
use think\Config;
use think\Container;
use think\Event;
use think\service\PaginatorService;
use Throwable;

class Sandbox
{
    /**
     * The app containers in different coroutine environment.
     *
     * @var array
     */
    protected $snapshots = [];

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
        Container::setInstance(function () {
            return $this->getApplication();
        });

        $this->setInitialConfig();
        $this->setInitialServices();
        $this->setInitialEvent();
        $this->setInitialResetters();
        // $this->setInitialCleans();

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


    public function run(Closure $callable, $fd = null, $persistent = false)
    {
        $this->init($fd);

        try {
            $this->getApplication()->invoke($callable, [$this]);
        } catch (Throwable $e) {
            $this->manager->logServerError($e);
        } finally {
            $this->clear(!$persistent);
        }
    }

    public function init($fd = null)
    {
        if (!is_null($fd)) {
            Context::setData('_fd', $fd);
        }
        $this->setInstance($app = $this->getApplication());
        $this->resetApp($app);
    }

    public function clear($snapshot = true)
    {
        if ($snapshot) {
            unset($this->snapshots[$this->getSnapshotId()]);
        }

        $this->setInstance($this->getBaseApp());
    }

    /**
     * 获取容器
     * @return App|null
     */
    public function getApplication()
    {
        $snapshot = $this->getSnapshot();
        if ($snapshot instanceof Container) {
            return $snapshot;
        }
        $snapshot = clone $this->getBaseApp();
        $this->setContext($snapshot);
        $this->resetApp($snapshot);

        return $snapshot;
    }

    protected function setContext(Container $snapshot)
    {
        Context::setData('__app', $snapshot);
        Context::setData('__destroy', new ContextDestroy($snapshot, $this->containerDestroy));
    }

    protected function getSnapshotId()
    {
        if ($fd = Context::getData('_fd')) {
            return "fd_" . $fd;
        } else {
            return Context::getCoroutineId();
        }
    }

    /**
     * Get current snapshot.
     * @return App|null
     */
    public function getSnapshot()
    {
        return $this->snapshots[$this->getSnapshotId()] ?? null;
    }

    public function setSnapshot(Container $snapshot)
    {
        $this->snapshots[$this->getSnapshotId()] = $snapshot;

        return $this;
    }

    /**
     * 重设容器实例
     * @param Container $app
     */
    public function setInstance(Container $app)
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
            ResetConfig::class,
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
