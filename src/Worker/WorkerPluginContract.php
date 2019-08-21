<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Worker;

use HZEX\TpSwoole\Manager;

/**
 * Interface WorkerContract
 * @package HZEX\TpSwoole\Worker
 */
interface WorkerPluginContract
{
    /**
     * 插件是否就绪
     * @param Manager $manager
     * @return bool
     */
    public function isReady(Manager $manager): bool;

    /**
     * 插件准备启动
     * @param Manager $manager
     * @return bool
     */
    public function prepare(Manager $manager): bool;
}
