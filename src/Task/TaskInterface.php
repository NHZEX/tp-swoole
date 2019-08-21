<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Task;

interface TaskInterface
{
    public static function delivery($arge);

    public function handle($arge);
}
