<?php

namespace app\Swoole;

use app\Swoole\ChildProcess\FileMonitor;
use Closure;
use Exception;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Runtime;
use Swoole\Server\Task;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;
use think\facade\Env;
use Throwable;

class Manager
{
    /** @var Manager */
    private $swoole;

    /** @var Http */
    private $http;

    /** @var int */
    private $pid;

    /** @var array 支持的响应事件 */
    protected $event = [
        'Start', 'Shutdown',
        'WorkerStart', 'WorkerStop', 'WorkerExit', 'WorkerError',
        'ManagerStart', 'ManagerStop',
        'Connect', 'Receive', 'Packet', 'Close',
        'Task', 'Finish', 'PipeMessage',
        'Request', 'Open', 'Message', 'HandShake',
    ];

    public function __construct(string $host, int $port, int $mode, int $sockType, array $option)
    {
        Runtime::enableCoroutine(true);

        $this->swoole = new Server($host, $port, $mode, $sockType);
        $this->swoole->set($option);

        $this->swoole->addProcess((new FileMonitor($this))->makeProcess());

        $this->swoole->on('Start', Closure::fromCallable([$this, 'onStart']));
        $this->swoole->on('ManagerStart', Closure::fromCallable([$this, 'onManagerStart']));
        $this->swoole->on('WorkerStart', Closure::fromCallable([$this, 'onWorkerStart']));
        $this->swoole->on('WorkerError', Closure::fromCallable([$this, 'onWorkerError']));

        $this->swoole->on('Task', Closure::fromCallable([$this, 'onTask']));
        $this->swoole->on('Finish', Closure::fromCallable([$this, 'onFinish']));

        $this->swoole->on('Request', Closure::fromCallable([$this, 'onRequest']));

        $this->swoole->on('Open', Closure::fromCallable([$this, 'onOpen']));
        $this->swoole->on('Message', Closure::fromCallable([$this, 'onMessage']));
        $this->swoole->on('Close', Closure::fromCallable([$this, 'onClose']));
    }

    /**
     * 获取SwooleServer实例
     * @return Server
     */
    public function getSwoole()
    {
        return $this->swoole;
    }

    /**
     * 启动服务
     */
    public function start()
    {
        $this->swoole->start();
    }

    /**
     * 主进程启动
     * @param Server $server
     */
    protected function onStart(Server $server)
    {
        swoole_set_process_name('php-ps: master');
        $this->pid = $server->master_pid;
    }

    /**
     * 管理进程启动
     * @param Server $server
     */
    protected function onManagerStart(Server $server)
    {
        swoole_set_process_name('php-ps: manager');
    }

    /**
     * 工作进程启动（Worker/Task）
     * @param Server $server
     * @param int    $workerId
     */
    protected function onWorkerStart(Server $server, int $workerId)
    {
        $type = $server->taskworker ? 'task' : 'worker';
        swoole_set_process_name("php-ps: {$type}#{$workerId}");
        if (false === $server->taskworker) {
            $this->http = new Http(Env::get('APP_PATH'));
            $this->http->httpStart($server, $workerId);
        }
    }

    /**
     * 任务处理回调
     * @param Server $server
     * @param Task   $task
     */
    protected function onTask(Server $server, Task $task)
    {
        //完成任务，结束并返回数据
        $task->finish(true);
    }

    /**
     * 任务完成响应
     * @param Server $server
     * @param int    $taskId
     * @param string $data
     */
    protected function onFinish(Server $server, int $taskId, string $data)
    {
    }

    /**
     * 工作进程异常（Worker/Task）
     * @param Server $server
     * @param int    $workerId
     * @param int    $workerPid
     * @param int    $exitCode
     * @param int    $signal
     */
    protected function onWorkerError(Server $server, int $workerId, int $workerPid, int $exitCode, int $signal)
    {
        echo "WorkerError: $workerId, pid: $workerPid, execCode: $exitCode, signal: $signal\n";
        if ($this->http instanceof Http) {
            $this->http->appShutdown();
        }
    }

    /**
     * 请求到达回调（Http）
     * @param Request  $request
     * @param Response $response
     * @throws Throwable
     */
    protected function onRequest(Request $request, Response $response)
    {
        //$response->end("<h1>Hello Swoole. #" . rand(1000, 9999) . "</h1>");
        $this->http->httpRequest($request, $response);
        $this->http->appShutdown();
    }

    /**
     * 连接建立回调（WebSocket）
     * @param Server  $server
     * @param Request $request
     */
    protected function onOpen(Server $server, Request $request)
    {
    }

    /**
     * 消息到达回调（WebSocket）
     * @param Server          $server
     * @param                 $frame
     * @throws Exception
     */
    protected function onMessage(Server $server, Frame $frame)
    {
    }

    /**
     * 连接关闭回调（WebSocket）
     * @param Server          $server
     * @param                 $fd
     */
    protected function onClose(Server $server, $fd)
    {
    }
}
