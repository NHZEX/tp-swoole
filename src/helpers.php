<?php

use think\Container;

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

if (!function_exists('debug_array')) {
    /**
     * @param      $data
     * @param bool $display
     * @return mixed
     */
    function debug_array(iterable $data, $display = true)
    {
        foreach ($data as &$item) {
            if (is_array($item)) {
                $item = debug_array($item);
            } else {
                $item = trim(debug_object($item));
            }
        }
        $content = $data;

        if ($display) {
            echo var_export($content, true);
        }
        return $content;
    }
}

if (!function_exists('debug_object')) {
    /**
     * @param      $object
     * @param bool $display
     * @return string|null
     */
    function debug_object($object, $display = true)
    {
        $content = null;
        if (false === is_object($object)) {
            $content = 'debug: ' . gettype($object) . PHP_EOL;
        } else {
            $content = 'debug: ' . get_class($object) . '#' . hash('crc32', spl_object_hash($object)) . PHP_EOL;
        }
        if ($display) {
            echo $content;
        }
        return $content;
    }
}

if (!function_exists('debug_object_trace')) {
    /**
     * @param      $object
     * @param bool $display
     * @return string|null
     */
    function debug_object_trace($object, $display = true)
    {
        $debug = debug_backtrace(0, 10);
        $debug = array_filter($debug, function ($val) {
            if (isset($val['function']) && 'debug_object' === $val['function']) {
                return false;
            }
            if (isset($val['file'])
                && (
                    false !== strpos($val['file'], 'think/Container.php')
                    || false !== strpos($val['file'], 'src/VirtualContainer.php')
                )
            ) {
                if (isset($val['class'])
                    && 'think\Container' === $val['class']
                    && (
                        'make' === $val['function']
                        || 'get' === $val['function']
                        || 'getObjectParam' === $val['function']
                        || 'bindParams' === $val['function']
                        || 'invokeMethod' === $val['function']
                        || 'invokeClass' === $val['function']
                    )
                ) {
                    return false;
                }
            }
            if (isset($val['file'])
                && false !== strpos($val['file'], 'framework/src/helper.php')
                && (
                    'app' === $val['function']
                    || 'think\Container' === $val['class']
                )
            ) {
                return false;
            }
            if (isset($val['file'])
                && false !== strpos($val['file'], 'src/think/Facade')
            ) {
                return false;
            }

            return true;
        });
        // var_dump($debug);
        $info = array_pop($debug);
        $info['file'] = $info['file'] ?? 'null';
        $info['line'] = $info['line'] ?? '0';
        $info['file'] = '...' . substr($info['file'], strlen($info['file']) - 20, strlen($info['file']));
        $info['class'] = $info['class'] ?? 'null';
        $info['function'] = $info['function'] ?? 'null';
        $info['type'] = $info['type'] ?? '@';
        $info['args'] = $info['args'] ?? [];
        array_walk_recursive($info['args'], function (&$value) {
            if (is_object($value)) {
                $value = get_class($value);
            }
        });
        $info['args'] = json_encode(
            $info['args'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_ERROR_RECURSION
        );
        $infostr = "{$info['file']}[{$info['line']}]#{$info['class']}{$info['type']}{$info['function']}**";
        $infostr .= "\t{$info['args']}";
        $infostr .= "\t";


        $content = null;
        if (false === is_object($object)) {
            $content = $infostr . '.debug: ' . gettype($object) . PHP_EOL;
        } else {
            $objid = hash('crc32', spl_object_hash($object));
            $content = $infostr . '.debug: ' . get_class($object) . '#' . $objid . PHP_EOL;
        }
        if ($display) {
            echo $content;
        }
        return $content;
    }
}

if (!function_exists('debug_closure')) {
    /** @noinspection PhpDocMissingThrowsInspection */
    /**
     * @param      $object
     * @param bool $display
     * @return string|null
     */
    function debug_closure($object, $display = true)
    {
        $content = null;
        if (false === $object instanceof Closure) {
            $content = 'debug: ' . gettype($object) . PHP_EOL;
        } else {
            /** @noinspection PhpUnhandledExceptionInspection */
            $ref = new ReflectionFunction($object);
            $thisClass = get_class($ref->getClosureThis());
            $content = "debug: $thisClass@{$ref->getStartLine()}-{$ref->getEndLine()}\n";
        }
        if ($display) {
            echo $content;
        }
        return $content;
    }
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
                return get_class($val) . ' = ' . hash('crc32', spl_object_hash($val));
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
                    return get_class($val) . ' = ' . hash('crc32', spl_object_hash($val));
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
                $value = debug_object($value, false);
            }
        });
        return $debug;
    }
}
