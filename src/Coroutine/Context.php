<?php

namespace HZEX\TpSwoole\Coroutine;

use Closure;
use Swoole\Coroutine;

class Context
{

    /**
     * The data in different coroutine environment.
     *
     * @var array
     */
    protected static $data = [];

    /**
     * Get data by current coroutine id.
     *
     * @param string $key
     *
     * @param null   $default
     * @return mixed|null
     */
    public static function getData(string $key, $default = null)
    {
        $ctx = Coroutine::getContext();
        return $ctx->$key ?? $default;
    }

    public static function hasData(string $key)
    {
        $ctx = Coroutine::getContext();
        return isset($ctx->$key);
    }

    public static function rememberData(string $key, $value)
    {
        if (self::hasData($key)) {
            return self::getData($key);
        }

        if ($value instanceof Closure) {
            // 获取缓存数据
            $value = $value();
        }

        self::setData($key, $value);

        return $value;
    }

    /**
     * Set data by current coroutine id.
     *
     * @param string $key
     * @param        $value
     */
    public static function setData(string $key, $value)
    {
        $ctx = Coroutine::getContext();
        $ctx->$key = $value;
    }

    /**
     * Remove data by current coroutine id.
     *
     * @param string $key
     */
    public static function removeData(string $key)
    {
        $ctx = Coroutine::getContext();
        unset($ctx->$key);
    }

    /**
     * Get data keys by current coroutine id.
     */
    public static function getDataKeys()
    {
        $ctx = Coroutine::getContext();
        return array_keys($ctx);
    }

    /**
     * Get current coroutine id.
     */
    public static function getCoroutineId()
    {
        return Coroutine::getuid();
    }
}
