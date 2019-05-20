<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Facade;

use think\Facade;

/**
 * Class Manager
 * @package HZEX\TpSwoole\Facade
 * @method \HZEX\TpSwoole\Manager instance() static
 */
class Manager extends Facade
{
    public static function getFacadeClass()
    {
        return \HZEX\TpSwoole\Manager::class;
    }
}
