<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Container\Destroy;

use Exception;
use Swoole\Coroutine;
use think\App;
use think\Container;
use think\db\Connection;

class DestroyDbConnection implements DestroyContract
{
    /**
     * 销毁所有Db连接实例
     *
     * @param Container|App $container
     * @throws Exception
     */
    public function handle(Container $container): void
    {
        $cxt = Coroutine::getContext();
        if (isset($cxt['__orm_instance'])) {
            foreach ($cxt['__orm_instance'] as $item) {
                if ($item instanceof Connection) {
                    $item->close();
                } else {
                    throw new Exception('unknown connection object: ' . get_class($item));
                }
            }
        }
    }
}
