<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Facade;

use think\Facade;

/**
 * Class Sandbox
 * @package HZEX\TpSwoole\Facade
 * @method void init($fd = null) static
 */
class Sandbox extends Facade
{
    protected static function getFacadeClass()
    {
        return \HZEX\TpSwoole\Sandbox::class;
    }
}
