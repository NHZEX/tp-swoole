<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Process\Child;

use HZEX\TpSwoole\Process\ChildProcess;
use Swoole\Process;

class CentralSwitch extends ChildProcess
{

    /**
     * @param Process $process
     * @return bool
     */
    protected function processBox(Process $process)
    {
        // TODO: Implement processBox() method.
    }
}
