<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Process\Child;

use HZEX\TpSwoole\Process\ChildProcess;
use SplFileInfo;
use Swoole\Coroutine;
use Swoole\Process;
use Symfony\Component\Finder\Finder;

class FileWatch extends ChildProcess
{
    private $config = [];
    /**
     * @var int
     */
    private $interval = 1;
    /**
     * @var int
     */
    private $lastMtime = 0;
    /**
     * @var Finder
     */
    private $finder;

    public function setConfig(array $config)
    {
        $this->config = $config;
    }

    protected function init()
    {
    }

    /**
     * @param Process $process
     * @return bool
     */
    protected function processMain(Process $process)
    {
        $this->finder = new Finder();
        $this->finder->files()
            ->name($this->config['name'] ?? ['*.php'])
            ->notName($this->config['notName'] ?? [])
            ->in($this->config['include'] ?? [app_path()])
            ->exclude($this->config['exclude'] ?? [])
            ->files();

        $ignoreFiles = array_flip(get_included_files());
        swoole_set_process_name('php-ps: FileWatch');
        echo "FileMonitor Process: {$process->pid}, Scanning...\n";

        while (true) {
            Coroutine::sleep($this->interval);
            if (false === $this->finder->hasResults()) {
                continue;
            }
            $lastMtime = $this->lastMtime;
            /** @var SplFileInfo $file */
            foreach ($this->finder as $file) {
                // check mtime
                if ($lastMtime < $file->getMTime()) {
                    if ($this->lastMtime === 0) {
                        $lastMtime = $file->getMTime();
                    } else {
                        $lastMtime = $file->getMTime();
                        if (isset($ignoreFiles[(string) $file])) {
                            $this->manager->getOutput()->writeln("[update] $file <warning>ignore</warning>");
                            continue;
                        } else {
                            $this->manager->getOutput()->writeln("[update] $file <comment>reload</comment>");
                            $this->swoole->reload();
                            break;
                        }
                    }
                }
            }
            $this->lastMtime = $lastMtime;
        }
        return true;
    }

    /**
     * 进程停止
     */
    protected function processExit(): void
    {
    }
}
