<?php

namespace HZEX\TpSwoole\Resetters;

use think\App;
use think\Event;

/**
 * Class RebindRouterContainer
 * @package think\swoole\resetters
 */
class RebindEventContainer implements ResetterContract
{
    public function handle(App $app): void
    {
        $route = $app->make(Event::class);

        $closure = function () use ($app) {
            /** @noinspection PhpUndefinedFieldInspection */
            $this->app = $app;
        };

        $resetEvent = $closure->bindTo($route, $route);
        $resetEvent();
    }
}
