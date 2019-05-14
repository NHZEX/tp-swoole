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

namespace app\Swoole\Command;

use app\Swoole\Manager;
use Swoole\Process;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Config;
use think\facade\Env;

/**
 * Swoole 命令行，支持操作：start|stop|restart|reload
 * 支持应用配置目录下的swoole_server.php文件进行参数配置
 */
class ServerCommand extends Command
{
    protected $host = '0.0.0.0';
    protected $port = 9502;
    protected $mode = SWOOLE_PROCESS;
    protected $sockType = SWOOLE_SOCK_TCP;

    protected $option = [];

    public function configure()
    {
        $this->setName('server')
            ->addArgument('action', Argument::OPTIONAL, "conf|start|stop|restart|reload", 'start')
            ->addOption('host', 'H', Option::VALUE_OPTIONAL, 'the host of swoole server.', null)
            ->addOption('port', 'p', Option::VALUE_OPTIONAL, 'the port of swoole server.', null)
            ->addOption('daemon', 'd', Option::VALUE_NONE, 'Run the swoole server in daemon mode.')
            ->setDescription('Swoole Server for ThinkPHP');
    }

    /**
     * 初始化
     */
    protected function init()
    {
        $config = Config::pull('swoole');

        // 获取 host 设置
        if ($this->input->hasOption('host')) {
            $this->host = $this->input->getOption('host');
        } else {
            $this->host = isset($config['host']) ? $config['host'] : '127.0.0.1';
        }
        // 获取 port 设置

        if ($this->input->hasOption('port')) {
            $this->port = (int) $this->input->getOption('port');
        } else {
            $this->port = (int) (isset($config['port']) ? $config['port'] : 9501);
        }

        // 加载 swoole 选项
        $this->option = $config['option'];

        // 设置静态资源目录
        if (false === isset($this->option['document_root'])) {
            $this->option['document_root'] = Env::get('root_path') . 'public';
        }

        // 开启守护进程模式
        if ($this->input->hasOption('daemon')) {
            $this->option['daemonize'] = true;
        }
    }

    /**
     * @return string
     */
    protected function getHost()
    {
        if ($this->input->hasOption('host')) {
            $host = $this->input->getOption('host');
        } else {
            $host = !empty($this->option['host']) ? $this->option['host'] : '0.0.0.0';
        }

        return $host;
    }

    /**
     * @return int
     */
    protected function getPort()
    {
        if ($this->input->hasOption('port')) {
            $port = $this->input->getOption('port');
        } else {
            $port = !empty($this->option['port']) ? $this->option['port'] : 9501;
        }

        return (int) $port;
    }

    /**
     * 获取主进程PID
     * @access protected
     * @return int
     */
    protected function getMasterPid()
    {
        $pidFile = $this->option['pid_file'];

        if (is_file($pidFile)) {
            $masterPid = (int) file_get_contents($pidFile);
        } else {
            $masterPid = 0;
        }

        return $masterPid;
    }

    public function execute(Input $input, Output $output)
    {
        $action = $input->getArgument('action');

        $this->init();

        if (in_array($action, ['conf', 'start', 'stop', 'reload', 'restart'])) {
            $this->$action();
        } else {
            $output->writeln("<error>Invalid argument action:{$action}, Expected conf|start|stop|restart|reload .</error>");
        }
    }

    /**
     * 删除PID文件
     * @access protected
     * @return void
     */
    protected function removePid()
    {
        $masterPid = $this->option['pid_file'];

        if (is_file($masterPid)) {
            unlink($masterPid);
        }
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

        return Process::kill($pid, 0);
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
     */
    protected function start()
    {
        $pid = $this->getMasterPid();

        if ($this->isRunning($pid)) {
            $this->output->writeln('<error>swoole server process is already running.</error>');
            return false;
        }

        $this->output->writeln('Starting swoole server...');

        $host = $this->getHost();
        $port = $this->getPort();

        $server = new Manager($host, $port, $this->mode, $this->sockType, $this->option);

        $this->output->writeln("Swoole Http && Websocket started: <{$host}:{$port}>");
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

        $this->output->writeln('Stopping swoole server...');

        Process::kill($pid, SIGTERM);
        $this->removePid();

        $this->output->writeln('> success');
        return true;
    }
}
