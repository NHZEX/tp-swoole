<?php
declare(strict_types=1);

namespace HZEX\TpSwoole;

use ArrayAccess;
use ArrayIterator;
use Closure;
use Co;
use Countable;
use Exception;
use HZEX\TpSwoole\Container\Destroy\DestroyContract;
use HZEX\TpSwoole\Container\Destroy\DestroyDbConnection;
use HZEX\TpSwoole\Coroutine\CoDestroy;
use HZEX\TpSwoole\Tp\Orm\Db;
use HZEX\TpSwoole\Worker\ConnectionPool;
use IteratorAggregate;
use Psr\Container\ContainerInterface;
use ReflectionException;
use ReflectionObject;
use RuntimeException;
use think\App;
use think\Config;
use think\Console;
use think\Container;
use think\Env;
use think\Event;
use think\Lang;
use Traversable;
use unzxin\zswCore\Event as SwooleEvent;

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
     * 协程明确销毁的实例
     * @var DestroyContract[]
     */
    private $containerDestroy = [];

    /**
     * 穿透容器副本的实例
     * @var array
     */
    private static $penetrates = [
        ContainerInterface::class,
        Container::class,
        App::class,
        VirtualContainer::class,
        Config::class,
        Console::class,
        Env::class,
        Event::class,
        Lang::class,
        SwooleEvent::class,
        Manager::class,
        ConnectionPool::class,
        'swoole.server',
        Db::class,
    ];

    /**
     * 加载虚拟容器配置
     */
    public static function loadConfiguration()
    {
        /** @var Config $config */
        $config = static::$vinstance->make('config');
        $penetrates = $config->get('swoole.penetrates', []);
        self::$penetrates = array_merge(self::$penetrates, $penetrates);
        $destroys = $config->get('swoole.container.destroy', []);
        self::$vinstance->setInitialDestroys($destroys);
    }

    /**
     * @param string $className
     */
    public static function addPenetrate(string $className)
    {
        self::$penetrates[] = $className;
    }

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
     * 设置容器销毁后的动作
     * @param array $destroys
     */
    private function setInitialDestroys(array $destroys)
    {
        $defaultDestroys = [
            DestroyDbConnection::class,
        ];

        $destroys = array_merge($defaultDestroys, $destroys);

        foreach ($destroys as $destroy) {
            $destroyClass = $this->make($destroy);
            if (!$destroyClass instanceof DestroyContract) {
                throw new RuntimeException("{$destroy} must implement " . DestroyContract::class);
            }
            $this->containerDestroy[$destroy] = $destroyClass;
        }
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
            if (in_array($class, self::$penetrates)) {
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

        if (-1 === $cid) {
            return static::$vinstance;
        }

        $context = Co::getContext();

        if (false === isset($context['__app'])) {
            $context['__app'] = $app = static::$vinstance->newCloneContainer();
            $context['__destroy'] = new CoDestroy($app, static::$vinstance->containerDestroy);
        }

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

        throw new Exception('无效操作：协程中不能重新设置容器实例');
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

    public function runningInConsole()
    {
        return parent::runningInConsole() && !exist_swoole();
    }
}
