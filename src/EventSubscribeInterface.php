<?php
declare(strict_types=1);

namespace HZEX\TpSwoole;

interface EventSubscribeInterface
{
    public function subscribe(Event $event): void;
}
