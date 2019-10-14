<?php

namespace HZEX\TpSwoole\Resetters;

use HZEX\TpSwoole\Contract\ResetterInterface;
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
     * @param App $container
     */
    public function handle(App $container): void
    {
//        foreach ($sandbox->getServices() as $service) {
//            $this->rebindServiceContainer($app, $service);
//            if (method_exists($service, 'register')) {
//                $service->register();
//            }
//            if (method_exists($service, 'boot')) {
//                $app->invoke([$service, 'boot']);
//            }
//        }
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
