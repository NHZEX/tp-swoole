<?php

namespace HZEX\TpSwoole\Resetters;

use ReflectionException;
use think\App;
use think\Container;
use think\Http;
use function HuangZx\ref_get_prop;

/**
 * Class RebindHttpContainer
 * @package think\swoole\resetters
 * @property Container $app;
 */
class ResetHttp implements ResetterContract
{
    /**
     * @param App $app
     * @throws ReflectionException
     */
    public function handle(App $app): void
    {
        $http = $app->make(Http::class);
        ref_get_prop($http, 'app')->setValue($app);
    }
}