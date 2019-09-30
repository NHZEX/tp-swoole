<?php

namespace HZEX\TpSwoole\Resetters;

use HZEX\TpSwoole\Contract\ResetterInterface;
use HZEX\TpSwoole\Sandbox;
use think\Container;

class ClearInstances implements ResetterInterface
{
    public function handle(Container $app, Sandbox $sandbox)
    {
        $instances = $sandbox->getConfig()->get('swoole.instances', []);

        foreach ($instances as $instance) {
            $app->delete($instance);
        }

        return $app;
    }
}
