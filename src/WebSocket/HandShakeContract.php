<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

namespace HZEX\TpSwoole\WebSocket;

use Swoole\Http\Request;
use Swoole\Http\Response;

interface HandShakeContract
{
    /**
     * "onHandShake" listener.
     *
     * @param Request  $request
     * @param Response $response
     * @return bool
     */
    public function onHandShake(Request $request, Response $response): bool;
}
