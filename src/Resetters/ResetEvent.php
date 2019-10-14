<?php

namespace HZEX\TpSwoole\Resetters;

use HZEX\TpSwoole\Contract\ResetterInterface;
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
     * @param App $app
     * @throws ReflectionException
     */
    public function handle(App $app): void
    {
        $event = $app->make(Event::class);
        ref_get_prop($event, 'app')->setValue($app);
    }
}
