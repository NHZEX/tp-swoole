<?php

use HZEX\TpSwoole\Service;

define('HZEX_SWOOLE_ENABLE', extension_loaded('swoole') && 'cli' === PHP_SAPI);

/**
 * 是否存在 swoole
 * @return bool
 */
function exist_swoole(): bool
{
    static $exist;
    if (null === $exist) {
        $exist = extension_loaded('swoole');
    }
    return $exist;
}

/**
 * 获取内存统计
 */
function stats_memory()
{
    static $max = 0;
    static $last = 0;
    $curr = memory_get_usage();
    if ($curr > $max) {
        $max = $curr;
    }
    if ($curr - $last !== 0) {
        $diff = $curr - $last;
        $last = $curr;
    }
    dump(sprintf('[mem#%d] max: %d (%d), inc: %+d', Service::getServer()->worker_id, $max, $curr, $diff ?? 0));
}
