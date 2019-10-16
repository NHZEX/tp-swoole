<?php

namespace HZEX\TpSwoole\Resetters;

use HZEX\TpSwoole\Contract\ResetterInterface;
use HZEX\TpSwoole\Sandbox;
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
class ResetHttp implements ResetterInterface
{
    /**
     * "handle" function for resetting app.
     *
     * @param Container|App $container
     * @param Sandbox       $sandbox
     * @throws ReflectionException
     */
    public function handle(App $container, Sandbox $sandbox): void
    {
        $http = $container->make(Http::class);
        ref_get_prop($http, 'app')->setValue($container);
    }
}
