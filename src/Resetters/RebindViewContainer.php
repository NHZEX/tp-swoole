<?php

namespace HZEX\TpSwoole\Resetters;

use think\App;

/**
 * Class RebindRouterContainer
 * @package think\swoole\resetters
 */
class RebindViewContainer implements ResetterContract
{
    public function handle(App $app): void
    {
        // view 不需要进行任何重置
    }
}
