<?php

namespace HZEX\TpSwoole\Resetters2;

use HZEX\TpSwoole\Tp\Request;
use think\App;
use think\Route;

/**
 * Class RebindRouterContainer
 * @package think\swoole\resetters
 */
class RebindRouterContainer implements ResetterContract
{
    public function handle(App $app): void
    {
        $route = $app->make(Route::class);

        $closure = function () use ($app) {
            $this->app = $app;
            $this->request = $app->make(Request::class);
        };

        $resetRouter = $closure->bindTo($route, $route);
        $resetRouter();
    }
}
