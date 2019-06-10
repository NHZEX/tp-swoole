<?php

namespace HZEX\TpSwoole\Resetters2;

use think\App;

interface ResetterContract
{
    /**
     * "handle" function for resetting app.
     *
     * @param App $container
     */
    public function handle(App $container): void;
}
