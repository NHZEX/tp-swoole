<?php

namespace HZEX\TpSwoole\Resetters;

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
            /** @noinspection PhpUndefinedFieldInspection */
            $this->app = $app;
            /** @noinspection PhpUndefinedFieldInspection */
            $this->request = $app->make(Request::class);
        };

        $resetRouter = $closure->bindTo($route, $route);
        $resetRouter();
    }
}
