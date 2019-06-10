<?php
declare(strict_types=1);

namespace HZEX\TpSwoole;

use ArrayAccess;
use ArrayIterator;
use Closure;
use Co;
use Countable;
use Exception;
use IteratorAggregate;
use ReflectionException;
use ReflectionObject;
use think\App;
use think\Cache;
use think\Config;
use think\Console;
use think\Container;
use think\Env;
use think\Event;
use think\Lang;
use Traversable;

/**
 * Class VirtualContainer
 * @mixin App
 * @package HZEX\TpSwoole
 */
class VirtualContainer extends App implements ArrayAccess, IteratorAggregate, Countable
{
    /**
     * 容器对象实例
     * @var VirtualContainer
     */
    protected static $vinstance;

    /**
     * 穿透容器副本的实例
     * @var array
     */
    private $penetrates = [
        App::class,
        Container::class,
        VirtualContainer::class,
        Config::class,
        Cache::class,
        Console::class,
        Env::class,
        Event::class,
        \HZEX\TpSwoole\Event::class,
        Lang::class,
        Manager::class,
        'swoole.server',
    ];

    public function __construct(string $rootPath = '')
    {
        parent::__construct($rootPath);

        $this->instance(App::class, $this);
        $this->instance(Container::class, $this);
        $this->instance(VirtualContainer::class, $this);

        parent::setInstance(Closure::fromCallable([self::class, 'getInstance']));

        static::$vinstance = $this;
    }

    /**
     * @return Container
     * @throws ReflectionException
     */
    public function newCloneContainer()
    {
        $newContainer = clone $this;

        $refNewContainer = new ReflectionObject($newContainer);
        $instances = $refNewContainer->getProperty('instances');
        $instances->setAccessible(true);
        /** @var array $instancesVlaue */
        $instancesVlaue = $instances->getValue($newContainer);
        foreach ($instancesVlaue as $class => $object) {
            if (in_array($class, $this->penetrates)) {
                $instancesVlaue[$class] = $object;
            } else {
                $instancesVlaue[$class] = clone $object;
            }
        }
        $instances->setValue($newContainer, $instancesVlaue);


        $newContainer->instance(App::class, $newContainer);
        $newContainer->instance(Container::class, $newContainer);
        $newContainer->instance(VirtualContainer::class, $newContainer);

        return $newContainer;
    }

    /**
     * 获取当前容器的实例（单例）
     * @access public
     * @return Container
     * @throws ReflectionException
     */
    public static function getInstance(): Container
    {
        $cid = Co::getCid();
        // echo __METHOD__ . '=' . $cid . PHP_EOL;

        if (-1 === $cid) {
            return static::$vinstance;
        }

        $context = Co::getContext();

        if (false === isset($context['__app'])) {
            $context['__app'] = static::$vinstance->newCloneContainer();
            //echo 'initGetInstance' . debug_object($context['__app'], false);
        }

        // echo "getInstance#{$cid}#" . debug_object($context['__app']);
        return $context['__app'];
    }

    /**
     * 设置当前容器的实例
     * @access public
     * @param App $instance
     * @return void
     * @throws Exception
     */
    public static function setInstance($instance): void
    {
        $cid = Co::getCid();

        if (-1 === $cid && $instance instanceof VirtualContainer) {
            parent::setInstance($instance);
            return;
        }

        // Co::getContext()['__app'] = $instance;
        throw new Exception('无效操作');
    }

    /**
     * @param $name
     * @param $value
     * @throws ReflectionException
     */
    public function __set($name, $value)
    {
        static::getInstance()->bind($name, $value);
    }

    /**
     * @param $name
     * @return object
     * @throws ReflectionException
     */
    public function __get($name)
    {
        return static::getInstance()->get($name);
    }

    /**
     * @param $name
     * @return bool
     * @throws ReflectionException
     */
    public function __isset($name): bool
    {
        return static::getInstance()->exists($name);
    }

    /**
     * @param $name
     * @throws ReflectionException
     */
    public function __unset($name)
    {
        static::getInstance()->delete($name);
    }

    /**
     * Whether a offset exists
     * @param mixed $offset
     * @return bool
     * @throws ReflectionException
     */
    public function offsetExists($offset)
    {
        return static::getInstance()->exists($offset);
    }

    /**
     * Offset to retrieve
     * @param mixed $offset
     * @return mixed
     * @throws ReflectionException
     */
    public function offsetGet($offset)
    {
        return static::getInstance()->make($offset);
    }

    /**
     * Offset to set
     * @param mixed $offset
     * @param mixed $value
     * @throws ReflectionException
     */
    public function offsetSet($offset, $value)
    {
        static::getInstance()->bind($offset, $value);
    }

    /**
     * Offset to unset
     * @param mixed $offset
     * @throws ReflectionException
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
}
