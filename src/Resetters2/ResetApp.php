<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Resetters2;

use ReflectionException;
use ReflectionObject;
use think\App;

class ResetApp implements ResetterContract
{

    /**
     * "handle" function for resetting app.
     *
     * @param App $app
     */
    public function handle(App $app): void
    {
        // 重置应用的开始时间和内存占用
        try {
            $ref = new ReflectionObject($app);
            $refBeginTime = $ref->getProperty('beginTime');
            $refBeginTime->setAccessible(true);
            $refBeginTime->setValue($app, microtime(true));
            $refBeginMem = $ref->getProperty('beginMem');
            $refBeginMem->setAccessible(true);
            $refBeginMem->setValue($app, memory_get_usage());
        } catch (ReflectionException $e) {
        }
    }
}
