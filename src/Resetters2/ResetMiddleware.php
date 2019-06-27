<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Resetters2;

use think\App;

class ResetMiddleware implements ResetterContract
{
    /**
     * "handle" function for resetting app.
     *
     * @param App $app
     */
    public function handle(App $app): void
    {
        $app->middleware->setApp($app);
    }
}
