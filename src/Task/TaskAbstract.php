<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Task;

use HZEX\TpSwoole\Contract\TaskInterface;
use Swoole\Server;
use think\App;

abstract class TaskAbstract implements TaskInterface
{
    protected $server;
    protected $id;
    protected $worker_id;
    protected $flags;

    public static function delivery($arge)
    {
        /** @var Server $server */
        $server = App::getInstance()->make('swoole.server');

        $server->task([static::class, $arge]);
    }

    public function __construct(Server $server, Server\Task $task)
    {
        $this->server = $server;
        $this->id = $task->id;
        $this->worker_id = $task->worker_id;
        $this->flags = $task->flags;
    }
}
