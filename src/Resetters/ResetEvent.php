<?php

namespace HZEX\TpSwoole\Resetters;

use HZEX\TpSwoole\Contract\ResetterInterface;
use HZEX\TpSwoole\Sandbox;
use ReflectionException;
use think\App;
use think\Container;
use think\Event;
use function HuangZx\ref_get_prop;

/**
 * Class ResetEvent
 * @package think\swoole\resetters
 * @property Container $app;
 */
class ResetEvent implements ResetterInterface
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
        $event = $container->make(Event::class);
        ref_get_prop($event, 'app')->setValue($container);
    }
}
