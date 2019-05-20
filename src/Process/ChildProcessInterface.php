<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Process;

use Swoole\Process;

interface ChildProcessInterface
{
    /**
     * @return string
     */
    public function pipeName(): string;

    /**
     * @return Process
     */
    public function makeProcess(): Process;

    /**
     * @return Process|null
     */
    public function getProcess(): ?Process;
}
