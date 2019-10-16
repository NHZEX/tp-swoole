<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Resetters;

use HZEX\TpSwoole\Contract\ResetterInterface;
use HZEX\TpSwoole\Sandbox;
use ReflectionException;
use ReflectionObject;
use think\App;
use think\Container;

class ResetApp implements ResetterInterface
{

    /**
     * "handle" function for resetting app.
     *
     * @param Container|App $container
     * @param Sandbox       $sandbox
     */
    public function handle(App $container, Sandbox $sandbox): void
    {
        // 重置应用的开始时间和内存占用
        try {
            $ref = new ReflectionObject($container);
            $refBeginTime = $ref->getProperty('beginTime');
            $refBeginTime->setAccessible(true);
            $refBeginTime->setValue($container, microtime(true));
            $refBeginMem = $ref->getProperty('beginMem');
            $refBeginMem->setAccessible(true);
            $refBeginMem->setValue($container, memory_get_usage());
        } catch (ReflectionException $e) {
        }
    }
}
