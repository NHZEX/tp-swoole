<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace HZEX\TpSwoole\Command;

use Exception;
use HZEX\TpSwoole\Contract\ServiceHealthCheckInterface;
use HZEX\TpSwoole\Manager;
use Swoole\Process;
use Swoole\Server\Port;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Config;
use Throwable;

/**
 * Swoole 命令行，支持操作：start|stop|restart|reload
 * 支持应用配置目录下的swoole_server.php文件进行参数配置
 */
class ServerCommand extends Command
{
    protected $config = [];

    public function configure()
    {
        $this->setName('server')
            ->addArgument('action', Argument::OPTIONAL, "conf|start|stop|restart|reload|health", 'start')
            ->addOption('daemon', 'd', Option::VALUE_NONE, 'Run the swoole server in daemon mode.')
            ->addOption('no-check', 'c', Option::VALUE_NONE, 'no check environment')
            ->setDescription('Swoole Server for ThinkPHP');

        // 不执行事件循环
        swoole_event_exit();
    }

    public function execute(Input $input, Output $output)
    {
        $this->config = Config::get('swoole');
        $action = $input->getArgument('action');

        if (false == $input->getOption('no-check') && false === $this->environment()) {
            $this->output->error("环境不符合要求");
            return 1;
        }

        if (in_array($action, ['conf', 'start', 'stop', 'reload', 'restart', 'health'])) {
            if (false === $this->$action()) {
                return 1;
            }
            // 停止事件循环 TODO 未完全确定这个操作是正确的
            swoole_event_exit();
        } else {
            $output->writeln(
                "<error>Invalid argument action:{$action}, Expected conf|start|stop|restart|reload|health .</error>"
            );
        }
        return 0;
    }

    protected function environment()
    {
        $success = true;
        $this->output->info("----------------------- ENVIRONMENT -----------------------------");
        $extensions = ['swoole', 'pcntl', 'posix', 'redis'];
        foreach ($extensions as $extension) {
            $exist = extension_loaded($extension);
            if ($success && false === $exist) {
                $success = false;
            }
            $existText = $exist ? '[+]' : '<error>[-]</error>';
            $ver = phpversion($extension) ?: 'null';
            $this->output->info("$existText {$extension} ({$ver})");
        }

        $notExist = ['xdebug'];
        foreach ($notExist as $extension) {
            $exist = extension_loaded($extension);
            if ($success && $exist) {
                $success = false;
            }
            $existText = $exist ? '<error>[Err]</error>' : '[Yes]';
            $this->output->info("{$existText} Can't exist {$extension}");
        }
        $this->output->info("--------------------------- ---- --------------------------------");
        return $success;
    }

    /**
     * 生成配置文件
     */
    protected function conf()
    {
        // TODO 生成配置文件
    }

    /**
     * 启动server
     * @access protected
     * @return bool
     * @throws Exception
     */
    protected function start()
    {
        $pid = $this->getMasterPid();

        if ($this->isRunning($pid)) {
            $this->output->writeln('<error>swoole server process is already running.</error>');
            return false;
        }

        $this->output->writeln('Starting swoole server...');
        
        /** @var Manager $server */
        $server = $this->app->make(Manager::class);
        $server->setOutput($this->output);
        $server->initialize();

        /** @var Port $masterPorts */
        $masterPorts = $server->getSwoole()->ports[0];

        $this->output->writeln("Swoole Http && Websocket started: <{$masterPorts->host}:{$masterPorts->port}>");
        $this->output->writeln('You can exit with <info>`CTRL-C`</info>');

        $server->start();
        return true;
    }

    /**
     * 柔性重启server
     * @access protected
     * @return bool
     */
    protected function reload()
    {
        // 柔性重启使用管理PID
        $pid = $this->getMasterPid();

        if (!$this->isRunning($pid)) {
            $this->output->writeln('<error>no swoole server process running.</error>');
            return false;
        }

        $this->output->writeln('Reloading swoole server...');
        Process::kill($pid, SIGUSR1);
        $this->output->writeln('> success');
        return true;
    }

    /**
     * 重启server
     * @access protected
     * @return void
     * @throws Exception
     */
    protected function restart()
    {
        $pid = $this->getMasterPid();

        if ($this->isRunning($pid)) {
            $this->stop();
        }

        $this->start();
    }

    /**
     * 健康检查
     */
    protected function health(): bool
    {
        $pid = $this->getMasterPid();
        if (false === $this->isRunning($pid)) {
            $this->output->writeln('service is not running');
            return false;
        }

        if (false === empty($this->config['health'])) {
            /** @var ServiceHealthCheckInterface $health */
            $health = $this->app->make($this->config['health']);
            if (false === $health instanceof ServiceHealthCheckInterface) {
                $this->output->error('invalid service health check handle: ' . $this->config['health']);
                return false;
            }
            $result = $health->handle();
            $status = $result ? '<info>正常</info>' : "<highlight>异常-{$health->getCode()}</highlight>";
            $this->output->writeln("服务健康状态: [{$status}]");
            $this->output->write($health->getMessage());
            return $result;
        }
        return true;
    }

    /**
     * 停止server
     * @access protected
     * @return bool
     */
    protected function stop()
    {
        $pid = $this->getMasterPid();

        if (!$this->isRunning($pid)) {
            $this->output->writeln('<error>no swoole server process running.</error>');
            return false;
        }

        $this->output->writeln("Stopping swoole server#{$pid}...");

        $isRunning = $this->killProcess($pid, SIGTERM, 15);

        if ($isRunning) {
            $this->output->error('Unable to stop the swoole_http_server process.');
            return false;
        }

        $this->removePid();

        $this->output->writeln('> success');
        return true;
    }

    /**
     * Get Pid file path.
     *
     * @return string
     */
    protected function getPidPath()
    {
        return $this->config['server']['options']['pid_file'];
    }

    /**
     * 获取主进程PID
     * @access protected
     * @return int
     */
    protected function getMasterPid()
    {
        $pidFile = $this->getPidPath();

        if (file_exists($pidFile)) {
            $masterPid = (int) file_get_contents($pidFile);
        } else {
            $masterPid = 0;
        }

        return $masterPid;
    }

    /**
     * 删除PID文件
     * @access protected
     * @return void
     */
    protected function removePid()
    {
        $masterPid = $this->getPidPath();

        if (file_exists($masterPid)) {
            unlink($masterPid);
        }
    }

    /**
     * 杀死进程
     * @param     $pid
     * @param     $sig
     * @param int $wait
     * @return bool
     */
    protected function killProcess($pid, $sig, $wait = 0)
    {
        Process::kill($pid, $sig);

        if ($wait) {
            $start = time();

            do {
                if (!$this->isRunning($pid)) {
                    break;
                }

                usleep(100000);
            } while (time() < $start + $wait);
        }

        return $this->isRunning($pid);
    }

    /**
     * 判断PID是否在运行
     * @access protected
     * @param  int $pid
     * @return bool
     */
    protected function isRunning($pid)
    {
        if (empty($pid)) {
            return false;
        }

        try {
            return Process::kill($pid, 0);
        } catch (Throwable $e) {
            return false;
        }
    }
}
