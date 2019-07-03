<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Coroutine;

use Closure;
use HZEX\TpSwoole\Container\Destroy\DestroyContract;
use think\Container;

class CoDestroy
{
    /**
     * @var Container
     */
    private $app;

    /**
     * @var DestroyContract[]
     */
    private $destroys = [];

    /**
     * CoDestroy constructor.
     * @param Container         $container
     * @param DestroyContract[] $destroys
     */
    public function __construct(Container $container, array $destroys)
    {
        $this->app = $container;
        $this->destroys = $destroys;
    }

    /**
     * 动态添加销毁处理器
     * @param DestroyContract $contract
     */
    public function add(DestroyContract $contract)
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
    }
}
