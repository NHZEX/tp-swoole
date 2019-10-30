<?php

namespace HZEX\TpSwoole\Resetters;

use HZEX\TpSwoole\Contract\ResetterInterface;
use HZEX\TpSwoole\Sandbox;
use think\App;
use think\Container;

class ClearInstances implements ResetterInterface
{
    protected $default = ['log'];

    /**
     * "handle" function for resetting app.
     *
     * @param Container|App $container
     * @param Sandbox       $sandbox
     */
    public function handle(App $container, Sandbox $sandbox): void
    {
        $instances = array_merge($this->default, $sandbox->getConfig()->get('swoole.instances', []));

        foreach ($instances as $instance) {
            $container->delete($instance);
        }
    }
}
