<?php

namespace HZEX\TpSwoole\Resetters;

use HZEX\TpSwoole\Contract\ResetterInterface;
use think\App;

class ClearInstances implements ResetterInterface
{
    public function handle(App $container): void
    {
        $instances = $container->config->get('swoole.instances', []);

        foreach ($instances as $instance) {
            $container->delete($instance);
        }
    }
}
