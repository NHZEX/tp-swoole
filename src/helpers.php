<?php

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
