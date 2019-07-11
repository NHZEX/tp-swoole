<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Container\Destroy;

use think\App;
use think\Container;

interface DestroyContract
{
    /**
     * "handle" function for clean.
     *
     * @param Container|App $container
     */
    public function handle(Container $container): void;
}
