<?php
declare(strict_types=1);

namespace HZEX\TpSwoole;

use ArrayIterator;
use HZEX\TpSwoole\Plugins\Http;
use ReflectionException;
use ReflectionObject;
use think\App;
use Traversable;

/**
 * Class VirtualContainer
 * @mixin App
 * @package HZEX\TpSwoole
 */
class VirtualContainer extends App
{
    /**
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        static::getInstance()->bind($name, $value);
    }

    /**
     * @param $name
     * @return object
     */
    public function __get($name)
    {
        return static::getInstance()->get($name);
    }

    /**
     * @param $name
     * @return bool
     */
    public function __isset($name): bool
    {
        return static::getInstance()->exists($name);
    }

    /**
     * @param $name
     */
    public function __unset($name)
    {
        static::getInstance()->delete($name);
    }

    /**
     * Whether a offset exists
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return static::getInstance()->exists($offset);
    }

    /**
     * Offset to retrieve
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return static::getInstance()->make($offset);
    }

    /**
     * Offset to set
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        static::getInstance()->bind($offset, $value);
    }

    /**
     * Offset to unset
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        static::getInstance()->delete($offset);
    }

    /**
     * @return Traversable
     * @throws ReflectionException
     */
    public function getIterator()
    {
        $co = self::getInstance();
        return new ArrayIterator(self::getProtectionValue($co, 'instances'));
    }

    /**
     * Count elements of an object
     * @return int The custom count as an integer.
     * @throws ReflectionException
     */
    public function count()
    {
        $co = self::getInstance();
        return count(self::getProtectionValue($co, 'instances'));
    }

    /**
     * @param object $obj
     * @param        $property
     * @return mixed
     * @throws ReflectionException
     */
    public static function getProtectionValue(object $obj, $property)
    {
        $ref = new ReflectionObject($obj);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);
        return $prop->getValue($obj);
    }

    /**
     * @return bool
     */
    public function runningInConsole()
    {
        return parent::runningInConsole() && !Http::isHandleHttpRequest();
    }
}
