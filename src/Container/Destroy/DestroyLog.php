<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Container\Destroy;

use ReflectionException;
use think\App;
use think\Container;
use function HuangZx\ref_get_prop;

class DestroyLog implements DestroyContract
{
    /**
     * 销毁所有Db连接实例
     *
     * @param Container|App $container
     * @throws ReflectionException
     */
    public function handle(Container $container): void
    {
        if ($container->exists('log')) {
            $container->log->clear();
            ref_get_prop($container->log, 'drivers')->setValue([]);
        }
    }
}
