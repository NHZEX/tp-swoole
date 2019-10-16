<?php

namespace HZEX\TpSwoole\Resetters;

use HZEX\TpSwoole\Contract\ResetterInterface;
use HZEX\TpSwoole\Sandbox;
use think\App;
use think\Container;

/**
 * Class ResetService
 * @package think\swoole\resetters
 * @property Container $app;
 */
class ResetService implements ResetterInterface
{

    /**
     * "handle" function for resetting app.
     *
     * @param Container|App $container
     * @param Sandbox       $sandbox
     */
    public function handle(App $container, Sandbox $sandbox): void
    {
        foreach ($sandbox->getServices() as $service) {
            $this->rebindServiceContainer($container, $service);
            if (method_exists($service, 'register')) {
                $service->register();
            }
            if (method_exists($service, 'boot')) {
                $container->invoke([$service, 'boot']);
            }
        }
    }

    protected function rebindServiceContainer($app, $service)
    {
        $closure = function () use ($app) {
            $this->app = $app;
        };

        $resetService = $closure->bindTo($service, $service);
        $resetService();
    }
}
