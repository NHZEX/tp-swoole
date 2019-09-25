<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Process;

use HZEX\TpSwoole\Manager;
use HZEX\TpSwoole\ProcessPool;
use Swoole\Process;

interface SubProcessInterface
{
    /**
     * @param Manager     $manager
     * @param ProcessPool $pool
     * @return Process
     */
    public function makeProcess(Manager $manager, ProcessPool $pool): Process;
}
