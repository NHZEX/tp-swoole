<?php

namespace HZEX\TpSwoole\Contract;

use think\App;

interface ResetterInterface
{
    /**
     * "handle" function for resetting app.
     *
     * @param App $container
     */
    public function handle(App $container): void;
}
