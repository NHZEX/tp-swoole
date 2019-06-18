<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Container\Destroy;

use think\Container;

class DestroyDbConnection implements DestroyContract
{

    /**
     * "handle" function for clean.
     *
     * @param Container $container
     */
    public function handle(Container $container): void
    {
        $container->db->getConnection()->close();
    }
}