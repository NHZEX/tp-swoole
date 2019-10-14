<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Resetters;

use HZEX\TpSwoole\Contract\ResetterInterface;
use think\App;
use think\Validate;

class ResetValidate implements ResetterInterface
{
    public function handle(App $app): void
    {
        /** @var Validate $validate */
        $validate = $app->make(Validate::class);
        $validate->setRequest($app->request);
    }
}
