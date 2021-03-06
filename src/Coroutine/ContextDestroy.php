<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Coroutine;

use Closure;
use HZEX\TpSwoole\Contract\ContractDestroyInterface;
use think\App;
use think\Container;
use function gc_collect_cycles;
use function stats_memory;

class ContextDestroy
{
    /**
     * @var Container|App
     */
    private $app;

    /**
     * @var ContractDestroyInterface[]
     */
    private $destroys = [];

    private $config = [];

    private $debug = false;

    /**
     * CoDestroy constructor.
     * @param Container                  $container
     * @param ContractDestroyInterface[] $destroys
     */
    public function __construct(Container $container, array $destroys)
    {
        $this->app = $container;
        $this->destroys = $destroys;

        $this->config = $this->app->config->get('swoole', []);
        $this->debug = $this->app->isDebug();
    }

    /**
     * 动态添加销毁处理器
     * @param ContractDestroyInterface $contract
     */
    public function add(ContractDestroyInterface $contract)
    {
        $this->destroys[] = $contract;
    }

    /**
     * 动态添加销毁处理器
     * @param Closure $contract
     */
    public function addClosure(Closure $contract)
    {
        $this->destroys[] = $contract;
    }

    /**
     */
    public function __destruct()
    {
        foreach ($this->destroys as $destroy) {
            if ($destroy instanceof Closure) {
                $destroy($this->app);
            } else {
                $destroy->handle($this->app);
            }
        }

        // 强制释放容器实例
        $unInstances = function () {
            foreach (array_keys($this->{'instances'}) as $key) {
                unset($this->{'instances'}[$key]);
            }
        };
        $unInstances = $unInstances->bindTo($this->app, $this->app);
        $unInstances();

        // 调试模式下尽快执行垃圾回收
        if ($this->debug) {
            // 强制执行垃圾回收
            gc_collect_cycles();
        }

        if ($this->debug && ($this->config['tracker'] ?? false)) {
            stats_memory();
        }
    }
}
