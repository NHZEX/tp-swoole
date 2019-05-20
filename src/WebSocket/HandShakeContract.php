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

use Swoole\Websocket\Frame;
use think\Request;

interface HandShakeContract
{
    /**
     * "onOpen" listener.
     *
     * @param int     $fd
     * @param Request $request
     */
    public function onOpen($fd, Request $request);
}
