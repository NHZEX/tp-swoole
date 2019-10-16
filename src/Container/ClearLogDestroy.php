<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Container;

use HZEX\TpSwoole\Contract\ContractDestroyInterface;
use think\Container;

class ClearLogDestroy implements ContractDestroyInterface
{
    public function handle(Container $container): void
    {
        if ($container->exists('log')) {
            $container->log->clear();
        }
    }
}
