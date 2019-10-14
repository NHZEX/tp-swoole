<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Tp;

use think\Route;

class Http extends \think\Http
{
    /** @var Route */
    protected static $route;

    protected function loadRoutes(): void
    {
        if (!isset(self::$route)) {
            parent::loadRoutes();
            self::$route = clone $this->app->route;
        }
    }

    protected function dispatchToRoute($request)
    {
        if (isset(self::$route)) {
            $newRoute = clone self::$route;
            $app      = $this->app;
            $closure  = function () use ($app) {
                $this->app = $app;
            };

            $resetRouter = $closure->bindTo($newRoute, $newRoute);
            $resetRouter();

            $this->app->instance("route", $newRoute);
        }

        return parent::dispatchToRoute($request);
    }
}
