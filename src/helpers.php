<?php

use think\Container;
use function HuangZx\debug_value;

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

if (!function_exists('debug_container_instance')) {
    /**
     * @param Container $container
     * @param int       $workerId
     */
    function debug_container_instance(Container $container, int $workerId = -1)
    {
        if (-1 === $workerId && $container->exists('swoole.server')) {
            $workerId = $container->get('swoole.server')->worker_id;
        }
        $debug = array_map(function ($val) use ($workerId) {
            if (is_object($val)) {
                return get_class($val) . ' = #' . spl_object_id($val);
            } else {
                return $val;
            }
        }, (array) $container->getIterator());
        ksort($debug, SORT_STRING); // SORT_FLAG_CASE
        echo "($workerId)";
        print_r($debug);
    }
}

if (!function_exists('debug_container_bind')) {
    /**
     * @param Container $container
     * @param int       $workerId
     */
    function debug_container_bind(Container $container, int $workerId = -1)
    {
        if (-1 === $workerId && $container->exists('swoole.server')) {
            $workerId = $container->get('swoole.server')->worker_id;
        }
        $debug = '';
        $ref = new ReflectionObject($container);
        try {
            $refBind = $ref->getProperty('bind');
        } catch (ReflectionException $e) {
            $debug = 'debug_container_bind error!';
        }
        if (isset($refBind)) {
            $refBind->setAccessible(true);
            $bind = $refBind->getValue($container);

            $instances = array_map(function ($val) use ($workerId) {
                if (is_object($val)) {
                    return get_class($val) . ' = #' . spl_object_id($val);
                } else {
                    return $val;
                }
            }, (array) $container->getIterator());

            foreach ($bind as $key => &$value) {
                if ($value instanceof Closure) {
                    $tmp = $instances[$key] ?? 'null';
                    $value = '\Closure => ' . (is_string($tmp) ? $tmp : print_r($tmp, true));
                } elseif (is_string($value)) {
                    $value = "$value => " . ($instances[$value] ?? 'null');
                } else {
                    $value = serialize($value) . ($instances[$key] ?? 'null');
                }
            }

            ksort($bind, SORT_STRING); // SORT_FLAG_CASE
            $debug = $bind;
        }
        echo "($workerId)";
        print_r($debug);
    }
}

if (!function_exists('debug_backtrace_ex')) {
    function debug_backtrace_ex($limit = 0)
    {
        $limit = 0 === $limit ? 0 : $limit + 1;
        $debug = debug_backtrace(0, $limit);
        array_shift($debug);
        $debug = array_filter($debug, function ($value) {
            return ($value['class'] ?? '') !== 'think\\Container';
        });
        array_walk_recursive($debug, function (&$value) {
            if (is_object($value)) {
                $value = debug_value($value);
            }
        });
        return $debug;
    }
}
