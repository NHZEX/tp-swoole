<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Resetters;

use HZEX\TpSwoole\Contract\ResetterInterface;
use HZEX\TpSwoole\Sandbox;
use think\App;
use think\Container;

class ResetDb implements ResetterInterface
{

    /**
     * "handle" function for resetting app.
     *
     * @param Container|App $container
     * @param Sandbox       $sandbox
     */
    public function handle(App $container, Sandbox $sandbox): void
    {
        if ($container->exists('db')) {
            $container->db->setLog($container->log);
        }
    }
}
