<?php
declare(strict_types=1);

namespace HZEX\TpSwoole;

use HZEX\TpSwoole\Process\BaseSubProcess;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine\Socket;
use Swoole\Server;

/**
 * Class ProcessPool
 * @package HZEX\TpSwoole
 * TODO 支持单个进程模型多次启动
 */
class ProcessPool
{
    /**
     * @var int
     */
    protected $masterPid = 0;
    /**
     * @var Manager
     */
    protected $manager;
    /**
     * @var BaseSubProcess[]
     */
    protected $pool;
    /**
     * @var int
     */
    protected $workerNum = 0;
    /**
     * @var array
     */
    protected $workerIdMapping = [];
    /**
     * @var bool
     */
    protected $debug = false;
    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(Manager $manager)
    {
        $this->manager = $manager;
        $this->masterPid = getmypid();
    }

    /**
     * @return int
     */
    public function getMasterPid(): int
    {
        return $this->masterPid;
    }

    /**
     * @param bool $debug
     * @return $this
     */
    public function setDebug(bool $debug): self
    {
        $this->debug = $debug;
        return $this;
    }

    /**
     * @param LoggerInterface $logger
     * @return $this
     */
    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    public function add(BaseSubProcess $worker)
    {
        $workerId = $this->workerNum++;
        $worker->setWorkerId($workerId);
        $worker->setDebug($this->debug);
        $worker->setLogger($this->logger);
        $worker->makeProcess($this->manager, $this);
        $this->pool[$worker->pipeName()] = $worker;
        $this->workerIdMapping[$workerId] = $worker->pipeName();
    }

    /**
     * 获取工人ID
     * @param string $workerName
     * @return int
     */
    public function getWorkerId(string $workerName)
    {
        return $this->pool[$workerName]->getWorkerId();
    }

    /**
     * 获取工人命名
     * @param int $workerId
     * @return string
     */
    public function getWorkerName(int $workerId)
    {
        return $this->workerIdMapping[$workerId];
    }

    /**
     * 获取 IPC Socket
     * @param string $pipeName
     * @return Socket
     */
    public function getWorkerSocket(string $pipeName): ?Socket
    {
        $monitorProcess = $this->pool[$pipeName]->getProcess();
        if (!$monitorProcess) {
            return null;
        }
        return $monitorProcess->exportSocket();
    }

    public function mount(Server $server)
    {
        foreach ($this->pool as $worker) {
            $server->addProcess($worker->getProcess());
        }
    }
}
