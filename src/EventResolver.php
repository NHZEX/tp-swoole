<?php
declare(strict_types=1);

namespace HZEX\TpSwoole;

use think\App;
use unzxin\zswCore\Contract\EventResolverInterface;
use unzxin\zswCore\Event;

class EventResolver implements EventResolverInterface
{
    /**
     * 构建类
     * @param       $classNamse
     * @param Event $event
     * @return mixed
     */
    public function makeClass($classNamse, Event $event)
    {
        return App::getInstance()->make($classNamse);
    }

    /**
     * 调用方法
     * @param       $callable
     * @param array $vars
     * @param Event $event
     * @return mixed
     */
    public function invoke($callable, array $vars, Event $event)
    {
        return App::getInstance()->invoke($callable, $vars);
    }
}
