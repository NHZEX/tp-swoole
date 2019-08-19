<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Resetters;

use think\App;
use think\Model;

class ResetModel implements ResetterContract
{
    /**
     * "handle" function for resetting app.
     *
     * @param App $app
     */
    public function handle(App $app): void
    {
        Model::setDb($app->db);
        Model::setEvent($app->event);
    }
}
