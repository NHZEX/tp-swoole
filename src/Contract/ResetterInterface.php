<?php

namespace HZEX\TpSwoole\Contract;

use HZEX\TpSwoole\Sandbox;
use think\Container;

interface ResetterInterface
{
    /**
     * "handle" function for resetting app.
     *
     * @param Container $app
     * @param Sandbox   $sandbox
     */
    public function handle(Container $app, Sandbox $sandbox);
}
