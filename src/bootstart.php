<?php

use HZEX\TpSwoole\Service;
use think\App;

if (class_exists(App::class)) {
    App::getInstance()->make(Service::class)->register();
}