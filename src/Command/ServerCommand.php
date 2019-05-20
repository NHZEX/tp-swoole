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

use HZEX\TpSwoole\Manager;
use Swoole\Process;
use think\App;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Config;

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
            ->addArgument('action', Argument::OPTIONAL, "conf|start|stop|restart|reload", 'start')
            ->addOption('daemon', 'd', Option::VALUE_NONE, 'Run the swoole server in daemon mode.')
            ->setDescription('Swoole Server for ThinkPHP');

        // 不执行事件循环
        swoole_event_exit();
    }

    public function execute(Input $input, Output $output)
    {
        $action = $input->getArgument('action');

        if (false === $this->environment()) {
            $this->output->error("环境不符合要求");
            return 1;
        }
        $this->init();

        if (in_array($action, ['conf', 'start', 'stop', 'reload', 'restart'])) {
            $this->$action();
            if (false === in_array($action, ['start', 'restart'])) {
                // 不执行事件循环
                swoole_event_exit();
            }
        } else {
            $output->writeln(
                "<error>Invalid argument action:{$action}, Expected conf|start|stop|restart|reload .</error>"
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
        return $success;
    }

    /**
     * 初始化
     */
    protected function init()
    {
        $this->output->info("--------------------------- INIT --------------------------------");
        $this->config = Config::pull('swoole');

        // 开启守护进程模式
        if ($this->input->hasOption('daemon')) {
            $conf = Config::get('swoole.server.options', []);
            $conf['daemonize'] = true;
            Config::set('swoole.server.options', $conf);
        }
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

        $host = $this->config['server']['host'];
        $port = $this->config['server']['port'];

        /** @var Manager $server */
        $server = App::getInstance()->make(Manager::class);

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
