<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Container\Destroy;

use Exception;
use ReflectionException;
use ReflectionObject;
use think\App;
use think\Container;
use think\db\Connection;

class DestroyDbConnection implements DestroyContract
{

    /**
     * "handle" function for clean.
     *
     * @param Container|App $container
     * @throws ReflectionException
     * @throws Exception
     */
    public function handle(Container $container): void
    {
        $db = $container->db;
        $dbReflection = new ReflectionObject($db);
        $refInstance = $dbReflection->getProperty('instance');
        $refInstance->setAccessible(true);
        $instance = $refInstance->getValue($db);

        foreach ($instance as $item) {
            if ($item instanceof Connection) {
                $item->close();
            } else {
                throw new Exception('unknown connection object: ' . get_class($item));
            }
        }
    }
}
