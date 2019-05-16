<?php

use HZEX\TpSwoole\Service;
use think\App;

if (class_exists(App::class)) {
    (new Service())->register();
}