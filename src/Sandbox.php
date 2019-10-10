<?php

namespace HZEX\TpSwoole;

use HZEX\TpSwoole\Contract\ResetterInterface;
use RuntimeException;
use Swoole\Coroutine;
use think\App;
use think\Config;
use think\Container;
use think\Event;
use think\service\PaginatorService;

class Sandbox
{
    /**
     * The app containers in different coroutine environment.
     *
     * @var array
     */
    protected $snapshots = [];

    /** @var App */
    protected $app;

    /** @var Config */
    protected $config;

    /** @var Event */
    protected $event;

    protected $resetters = [];
    protected $services  = [];

    /**
     * 实例化沙箱
     * Sandbox constructor.
     * @param Container $app
     */
    public function __construct(Container $app)
    {
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
     * 初始化沙盒
     * @return $this
     */
    protected function initialize()
    {
        Container::setInstance(function () {
            if (-1 === Coroutine::getCid()) {
                return $this->getBaseApp();
            }
            return $this->getApplication();
        });

        $this->setInitialConfig();
        $this->setInitialServices();
        $this->setInitialEvent();
        $this->setInitialResetters();

        return $this;
    }

    /**
     * 初始化容器
     * @param null $fd
     */
    public function init($fd = null)
    {
        if (null !== $fd) {
            $cxt = Coroutine::getContext();
            $cxt['__fd'] = $fd;
        }
        $this->setInstance($app = $this->getApplication());
        $this->resetApp($app);
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
        $this->setSnapshot($snapshot);

        return $snapshot;
    }

    /**
     * 获取快照ID
     * @return string
     */
    protected function getSnapshotId()
    {
        $id = Coroutine::getCid();
        if (-1 !== $id) {
            $cxt = Coroutine::getContext();
            if (isset($cxt['__fd'])) {
                return "fd_{$cxt['__fd']}";
            }
        }
        return "cid_{$id}";
    }

    /**
     * 获取容器快照
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
     * 清理沙箱
     * @param bool $snapshot
     */
    public function clear($snapshot = true)
    {
        if ($snapshot) {
            unset($this->snapshots[$this->getSnapshotId()]);
        }

        $this->setInstance($this->getBaseApp());

        // TODO 未确定是否可以不进行垃圾回收
        if ($this->getBaseApp()->isDebug()) {
            // 强制执行垃圾回收
            gc_collect_cycles();
        }
    }

    /**
     * 设置当前容器实例
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
//            ClearInstances::class,
//            ResetConfig::class,
//            ResetEvent::class,
//            ResetService::class,
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
     * @param Container $app
     */
    protected function resetApp(Container $app)
    {
        foreach ($this->resetters as $resetter) {
            $resetter->handle($app, $this);
        }
    }

}
