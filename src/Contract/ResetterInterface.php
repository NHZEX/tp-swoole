<?php

namespace HZEX\TpSwoole\Contract;

use HZEX\TpSwoole\Sandbox;
use think\App;
use think\Container;

interface ResetterInterface
{
    /**
     * "handle" function for resetting app.
     *
     * @param Container|App $container
     * @param Sandbox       $sandbox
     */
    public function handle(App $container, Sandbox $sandbox): void;
}
