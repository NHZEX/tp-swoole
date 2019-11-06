<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Facade;

use Monolog\Logger;
use think\Facade;

/**
 * Class ServerLogger
 * @package HZEX\TpSwoole\Facade
 * @method Logger instance() static
 */
class ServerLogger extends Facade
{
    protected static function getFacadeClass()
    {
        return 'swoole.log';
    }
}
