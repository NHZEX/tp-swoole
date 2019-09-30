<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Facade;

use think\Facade;
use unzxin\zswCore\Event;

/**
 * Class Event
 * @package HZEX\TpSwoole\Facade
 * @method Event instance() static
 */
class SwooleEvent extends Facade
{
    protected static function getFacadeClass()
    {
        return 'swoole.event';
    }
}
