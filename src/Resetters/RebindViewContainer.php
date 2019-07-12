<?php

namespace HZEX\TpSwoole\Resetters;

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
            /** @noinspection PhpUndefinedFieldInspection */
            $this->app = $app;
        };

        $resetView = $closure->bindTo($view->engine, $view->engine);
        $resetView();
    }
}
