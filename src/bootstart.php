<?php

use HZEX\TpSwoole\Service;
use think\App;
use think\Container;

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
            $content = $ref->__toString() . PHP_EOL;
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
        $debug = array_map(function ($val) use ($workerId) {
            if (is_object($val)) {
                return get_class($val) . ' = ' . hash('crc32', spl_object_hash($val) . $workerId);
            } else {
                return $val;
            }
        }, $container->all());
        ksort($debug, SORT_STRING); // SORT_FLAG_CASE
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
                    return get_class($val) . ' = ' . hash('crc32', spl_object_hash($val) . $workerId);
                } else {
                    return $val;
                }
            }, $container->all());

            foreach ($bind as $key => &$value) {
                if ($value instanceof Closure) {
                    $value = '\Closure => ' . ($instances[$key] ?? 'null');
                } elseif (is_string($value)) {
                    $value = "$value => " . ($instances[$value] ?? 'null');
                } else {
                    $value = serialize($value) . ($instances[$key] ?? 'null');
                }
            }

            ksort($bind, SORT_STRING); // SORT_FLAG_CASE
            $debug = $bind;
        }
        print_r($debug);
    }
}

if (class_exists(App::class)) {
    App::getInstance()->make(Service::class)->register();
}