<?php
declare(strict_types=1);

namespace HZEX\TpSwoole;

use ArrayAccess;
use ArrayIterator;
use Closure;
use Countable;
use Exception;
use HZEX\TpSwoole\Container\Destroy\DestroyContract;
use HZEX\TpSwoole\Contract\ResetterInterface;
use HZEX\TpSwoole\Coroutine\CoConstruct;
use HZEX\TpSwoole\Coroutine\CoDestroy;
use HZEX\TpSwoole\Resetters\ResetApp;
use HZEX\TpSwoole\Resetters\ResetEvent;
use HZEX\TpSwoole\Tp\Pool\Db;
use HZEX\TpSwoole\Worker\ConnectionPool;
use HZEX\TpSwoole\Worker\Http;
use IteratorAggregate;
use Psr\Container\ContainerInterface;
use ReflectionException;
use ReflectionObject;
use RuntimeException;
use Swoole\Coroutine;
use think\App;
use think\Config;
use think\Console;
use think\Container;
use think\Env;
use think\Lang;
use Traversable;
use unzxin\zswCore\Event as SwooleEvent;
use function HuangZx\ref_get_prop;

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
        ContainerInterface::class, // 确认安全
        Container::class, // 确认安全
        App::class, // 确认安全
        VirtualContainer::class, // 确认安全
        Config::class, // 确认安全
        Console::class, // 确认安全
        Env::class, // 确认安全
        Lang::class,
        SwooleEvent::class, // 确认安全
        Manager::class, // 确认安全
        ConnectionPool::class, // 确认安全
        'swoole.server', // 确认安全
        Db::class,  // 确认安全
    ];

    /**
     * @var ResetterInterface[]
     */
    protected $resetters = [];

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
     * @return VirtualContainer
     */
    public static function getVinstance(): VirtualContainer
    {
        return self::$vinstance;
    }

    /**
     * @param string $className
     */
    public static function addPenetrate(string $className)
    {
        self::$penetrates[] = $className;
    }

    /**
     * @return array
     */
    public static function getPenetrates(): array
    {
        return self::$penetrates;
    }

    public function __construct(string $rootPath = '')
    {
        parent::__construct($rootPath);

        $this->instance(App::class, $this);
        $this->instance(Container::class, $this);
        $this->instance(VirtualContainer::class, $this);
        $this->setInitialResetters();

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

        $instancesRef = ref_get_prop($newContainer, 'instances');
        /** @var array $instances */
        $instances = $instancesRef->getValue();
        foreach ($instances as $class => $object) {
            if (in_array($class, self::$penetrates) || in_array(get_class($object), self::$penetrates)) {
                $instances[$class] = $object;
            } else {
                $instances[$class] = clone $object;
            }
        }
        $instancesRef->setValue($instances);

        $newContainer->instance(App::class, $newContainer);
        $newContainer->instance(Container::class, $newContainer);
        $newContainer->instance(VirtualContainer::class, $newContainer);
        $this->resetters($newContainer);
        return $newContainer;
    }

    protected function setInitialResetters()
    {
        $resetters = [
            ResetApp::class,
            ResetEvent::class,
        ];

        foreach ($resetters as $resetter) {
            $resetterClass = $this->make($resetter);
            if (!$resetterClass instanceof ResetterInterface) {
                throw new RuntimeException("{$resetter} must implement " . ResetterInterface::class);
            }
            $this->resetters[$resetter] = $resetterClass;
        }
    }

    protected function resetters(App $container)
    {
        foreach ($this->resetters as $resetter) {
            $resetter->handle($container);
        }
    }

    /**
     * 获取当前容器的实例（单例）
     * @access public
     * @return Container
     * @throws ReflectionException
     */
    public static function getInstance(): Container
    {
        $cid = Coroutine::getCid();

        if (-1 === $cid) {
            return static::$vinstance;
        }

        $context = Coroutine::getContext();

        if (false === isset($context['__app'])) {
            $context['__construct'] = new CoConstruct();
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
        $cid = Coroutine::getCid();

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

    /**
     * @return bool
     */
    public function runningInConsole()
    {
        return parent::runningInConsole() && !Http::isHandleHttpRequest();
    }
}
