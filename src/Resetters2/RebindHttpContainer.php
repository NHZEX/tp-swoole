<?php

namespace HZEX\TpSwoole\Resetters2;

use think\App;
use think\Container;
use think\Http;

/**
 * Class RebindHttpContainer
 * @package think\swoole\resetters
 * @property Container $app;
 */
class RebindHttpContainer implements ResetterContract
{

    public function handle(App $app): void
    {
        $http = $app->make(Http::class);

        $closure = function () use ($app) {
            $this->app = $app;
        };

        $resetHttp = $closure->bindTo($http, $http);
        $resetHttp();
    }
}
