<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Resetters;

use ReflectionException;
use think\App;
use function HuangZx\ref_get_prop;

class ResetMiddleware implements ResetterContract
{
    /**
     * "handle" function for resetting app.
     *
     * @param App $app
     * @throws ReflectionException
     */
    public function handle(App $app): void
    {
        $refApp = ref_get_prop($app->middleware, 'app');
        $refApp->setValue($app);
    }
}
