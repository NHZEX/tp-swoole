<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Facade;

use Swoole\Server as SwooleServer;
use think\Facade;

/**
 * Class Server
 * @package HZEX\TpSwoole\Facade
 * @method SwooleServer instance() static
 * @method bool bind(int $fd, int $uid) static
 * @method array getClientInfo(int $fd, int $extraData = 0, bool $ignoreError = false) static
 */
class Server extends Facade
{
    protected static function getFacadeClass()
    {
        return 'swoole.server';
    }

    /**
     * @param int $fd
     * @return int|null
     */
    public static function getClientUid(int $fd): ?int
    {
        $info = self::instance()->getClientInfo($fd);
        $uid = is_array($info) ? ($info['uid'] ?? null) : null;
        return $uid;
    }
}
