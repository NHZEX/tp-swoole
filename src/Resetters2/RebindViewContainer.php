<?php

namespace HZEX\TpSwoole\Resetters2;

use think\App;
use think\View;

/**
 * Class RebindRouterContainer
 * @package think\swoole\resetters
 */
class RebindViewContainer implements ResetterContract
{
    public function handle(App $app): void
    {
        /** @var View $view */
        $view = $app->make(View::class);

        $closure = function () use ($app) {
            $this->app = $app;
        };

        $resetView = $closure->bindTo($view->engine, $view->engine);
        $resetView();
    }
}
