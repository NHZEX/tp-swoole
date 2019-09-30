<?php

namespace HZEX\TpSwoole\Resetters;

use HZEX\TpSwoole\Contract\ResetterInterface;
use HZEX\TpSwoole\Sandbox;
use think\Container;

/**
 * Class ResetEvent
 * @package think\swoole\resetters
 * @property Container $app;
 */
class ResetEvent implements ResetterInterface
{

    public function handle(Container $app, Sandbox $sandbox)
    {
        $event = clone $sandbox->getEvent();

        $closure = function () use ($app) {
            $this->app = $app;
        };

        $resetEvent = $closure->bindTo($event, $event);
        $resetEvent();

        $app->instance('event', $event);

        return $app;
    }
}
