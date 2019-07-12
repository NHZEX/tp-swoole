<?php

namespace HZEX\TpSwoole\Tp;

use think\log\Channel;
use think\Manager;

class Log extends \think\Log
{
    public function createDriver(string $name)
    {
        $driver = Manager::createDriver($name);

        $lazy  = !$this->getChannelConfig($name, "realtime_write", false)
            && (!$this->app->runningInConsole() || exist_swoole());

        $allow = array_merge($this->getConfig("level", []), $this->getChannelConfig($name, "level", []));

        return new Channel($name, $driver, $allow, $lazy, $this->app->event);
    }
}
