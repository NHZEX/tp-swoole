<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Coroutine;

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
     */
    public function __destruct()
    {
        foreach ($this->destroys as $destroy) {
            $destroy->handle($this->app);
        }
    }
}
