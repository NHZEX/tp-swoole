<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Resetters2;

use think\App;
use think\Validate;

class RebindValidate implements ResetterContract
{
    public function handle(App $app): void
    {
        /** @var Validate $validate */
        $validate = $app->make(Validate::class);
        $validate->setRequest($app->request);
    }
}
