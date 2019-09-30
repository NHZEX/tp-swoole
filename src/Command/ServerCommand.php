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
use HZEX\TpSwoole\Facade\ServerLogger;
use HZEX\TpSwoole\Log\MonologConsoleHandler;
use HZEX\TpSwoole\Manager;
use HZEX\TpSwoole\PidManager;
use Swoole\Server\Port;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;

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

    /**
     * @param Input  $input
     * @param Output $output
     * @return int|null
     * @throws Exception
     */
    public function execute(Input $input, Output $output)
    {
        $this->config = $this->app->config->get('swoole');
        $action = $input->getArgument('action');

        if (false == $input->getOption('no-check') && false === $this->checkEnvironment()) {
            $this->output->error("环境不符合要求");
            return 1;
        }

        if (in_array($action, ['conf', 'start', 'stop', 'reload', 'restart', 'health'])) {
            if (false === $this->action($action)) {
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

    /**
     * @param string $action
     * @return mixed
     * @throws Exception
     */
    protected function action(string $action)
    {
        return $this->app->invokeMethod([$this, $action], [], true);
    }

    /**
     * @return bool
     */
    protected function checkEnvironment()
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
     * 启动server
     * @access protected
     * @param Manager    $manager
     * @param PidManager $pidManager
     * @return bool
     * @throws Exception
     */
    protected function start(Manager $manager, PidManager $pidManager)
    {
        if ($pidManager->isRunning()) {
            $this->output->writeln('<error>swoole server process is already running.</error>');
            return false;
        }

        $this->output->writeln('Starting swoole server...');

        $logger = ServerLogger::instance();
        // 设置日志
        if ($this->config['log']['console'] ?? false) {
            $handlerConsole = new MonologConsoleHandler($this->output);
            $handlerConsole->setEnable(true);
            $logger->pushHandler($handlerConsole);
        }
        // 设置输出
        $manager->setOutput($this->output);
        $manager->setLogger($logger);
        $manager->initialize();

        /** @var Port $masterPorts */
        $masterPorts = $manager->getSwoole()->ports[0];

        $this->output->writeln("Swoole Http && Websocket started: <{$masterPorts->host}:{$masterPorts->port}>");
        $this->output->writeln('You can exit with <info>`CTRL-C`</info>');

        $manager->start();
        return true;
    }

    /**
     * 柔性重启server
     * @access protected
     * @param PidManager $pidManager
     * @return bool
     */
    protected function reload(PidManager $pidManager)
    {
        if (!$pidManager->isRunning()) {
            $this->output->writeln('<error>no swoole server process running.</error>');
            return false;
        }

        $this->output->writeln('Reloading swoole server...');

        if (!$pidManager->killProcess(SIGUSR1)) {
            $this->output->error('> failure');

            return false;
        }

        $this->output->writeln('> success');
        return true;
    }

    /**
     * 重启server
     * @access protected
     * @param PidManager $pidManager
     * @return bool
     * @throws Exception
     */
    protected function restart(PidManager $pidManager)
    {
        if ($pidManager->isRunning()) {
            $this->action('stop');
        }

        $this->action('start');
        return true;
    }

    /**
     * 健康检查
     * @param PidManager $pidManager
     * @return bool
     */
    protected function health(PidManager $pidManager): bool
    {
        if (!$pidManager->isRunning()) {
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
     * @param PidManager $pidManager
     * @return bool
     */
    protected function stop(PidManager $pidManager)
    {
        if (!$pidManager->isRunning()) {
            $this->output->writeln('<error>no swoole server process running.</error>');
            return false;
        }

        $this->output->writeln("Stopping swoole server#{$pidManager->getMasterPid()}...");

        $isRunning = $pidManager->killProcess(SIGTERM, 15);

        if ($isRunning) {
            $this->output->error('Unable to stop the swoole_http_server process.');
            return false;
        }

        $pidManager->remove();

        $this->output->writeln('> success');
        return true;
    }
}
