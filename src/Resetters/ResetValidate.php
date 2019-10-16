<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Resetters;

use HZEX\TpSwoole\Contract\ResetterInterface;
use HZEX\TpSwoole\Sandbox;
use think\App;
use think\Container;
use think\Validate;

class ResetValidate implements ResetterInterface
{
    /**
     * "handle" function for resetting app.
     *
     * @param Container|App $container
     * @param Sandbox       $sandbox
     */
    public function handle(App $container, Sandbox $sandbox): void
    {
        /** @var Validate $validate */
        $validate = $container->make(Validate::class);
        $validate->setRequest($container->request);
    }
}
