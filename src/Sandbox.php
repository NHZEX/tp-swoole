<?php

namespace HZEX\TpSwoole;

use HZEX\TpSwoole\Contract\ResetterInterface;
use HZEX\TpSwoole\Resetters\ClearInstances;
use HZEX\TpSwoole\Resetters\ResetConfig;
use HZEX\TpSwoole\Resetters\ResetEvent;
use HZEX\TpSwoole\Resetters\ResetService;
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

    public function __construct(Container $app)
    {
        $this->setBaseApp($app);
        $this->initialize();
    }

    public function setBaseApp(Container $app)
    {
        $this->app = $app;

        return $this;
    }

    public function getBaseApp()
    {
        return $this->app;
    }

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

    public function init()
    {
        $this->setInstance($app = $this->getApplication());
        $this->resetApp($app);
    }

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

    public function clear($snapshot = true)
    {
        if ($snapshot) {
            unset($this->snapshots[$this->getSnapshotId()]);
        }

        $this->setInstance($this->getBaseApp());
        // TODO 调试模式下下执行，强制执行垃圾回收
        gc_collect_cycles();
    }

    public function setInstance(Container $app)
    {
        $app->instance('app', $app);
        $app->instance(Container::class, $app);
    }

    /**
     * Set initial config.
     */
    protected function setInitialConfig()
    {
        $this->config = clone $this->getBaseApp()->config;
    }

    protected function setInitialEvent()
    {
        $this->event = clone $this->getBaseApp()->event;
    }

    /**
     * Get config snapshot.
     */
    public function getConfig()
    {
        return $this->config;
    }

    public function getEvent()
    {
        return $this->event;
    }

    public function getServices()
    {
        return $this->services;
    }

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
     * Initialize resetters.
     */
    protected function setInitialResetters()
    {
        $app = $this->getBaseApp();

        $resetters = [
            ClearInstances::class,
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
     * @param Container $app
     */
    public function resetApp(Container $app)
    {
        foreach ($this->resetters as $resetter) {
            $resetter->handle($app, $this);
        }
    }

}
