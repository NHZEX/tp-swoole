<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Facade;

use think\Facade;

/**
 * Class Server
 * @package HZEX\TpSwoole\Facade
 */
class Server extends Facade
{
    protected static function getFacadeClass()
    {
        return 'swoole.server';
    }
}
