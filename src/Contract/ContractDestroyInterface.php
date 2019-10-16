<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Contract;

use think\App;
use think\Container;

interface ContractDestroyInterface
{
    /**
     * "handle" function for clean.
     *
     * @param Container|App $container
     */
    public function handle(Container $container): void;
}
