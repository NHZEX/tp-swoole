<?php
declare(strict_types=1);

namespace HZEX\TpSwoole;

use HZEX\TpSwoole\Command\ServerCommand;
use think\App;
use think\Console;

class Service
{
    protected $app;

    public function __construct()
    {
        $this->app = App::getInstance();
    }

    public function register()
    {
        $this->commands(ServerCommand::class);
    }

    /**
     * 添加指令
     * @access protected
     * @param array|string $commands 指令
     */
    protected function commands($commands)
    {
        $commands = is_array($commands) ? $commands : func_get_args();

        Console::addDefaultCommands($commands);
    }
}
