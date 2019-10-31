<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Tp;

use HZEX\TpSwoole\Plugins\Http;

class App extends \think\App
{
    public function runningInConsole()
    {
        return parent::runningInConsole() && !Http::isHandleHttpRequest();
    }
}
