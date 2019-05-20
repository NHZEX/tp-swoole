<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Facade;

use Swoole\Server as SwooleServer;
use think\Facade;

/**
 * Class Server
 * @package HZEX\TpSwoole\Facade
 * @method SwooleServer instance() static
 */
class Server extends Facade
{
    protected static function getFacadeClass()
    {
        return 'swoole.server';
    }
}
