<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Process\Sub;

use HZEX\TpSwoole\Process\BaseSubProcess;
use SplFileInfo;
use Swoole\Coroutine;
use Symfony\Component\Finder\Finder;

class FileWatch extends BaseSubProcess
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

    /**
     * @return bool
     */
    protected function worker()
    {
        $this->finder = new Finder();
        $this->finder->files()
            ->name($this->config['name'] ?? ['*.php'])
            ->notName($this->config['notName'] ?? [])
            ->in($this->config['include'] ?? [app_path()])
            ->exclude($this->config['exclude'] ?? [])
            ->files();

        $ignoreFiles = array_flip(get_included_files());

        $this->logger->info("FileMonitor Process: {$this->process->pid}, Scanning...");

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
                            $this->logger->info("[update] $file <warning>ignore</warning>");
                            continue;
                        } else {
                            $this->logger->info("[update] $file <comment>reload</comment>");
                            $this->manager->getSwoole()->reload();
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
     * 收到消息事件
     * @param        $data
     * @param string $form
     * @return bool
     */
    protected function onPipeMessage($data, ?string $form): bool
    {
        return true;
    }

    /**
     * 进程退出
     */
    protected function onExit(): void
    {
    }
}
