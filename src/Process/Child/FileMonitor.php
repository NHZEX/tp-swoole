<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Process\Child;

use HZEX\TpSwoole\Process\ChildProcess;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Swoole\Coroutine;
use Swoole\Process;
use think\facade\Env;

class FileMonitor extends ChildProcess
{
    private $path = [];
    private $ext = ['php'];
    private $interval = 2;
    private $lastMtime = 0;

    protected function init()
    {
        $rootPath = Env::get('ROOT_PATH');
        $this->path = [$rootPath . 'application', $rootPath . 'config', $rootPath . 'extend'];
    }

    /**
     * @param Process $process
     * @return bool
     */
    protected function processBox(Process $process)
    {
        $ignoreFiles = array_flip(get_included_files());
        swoole_set_process_name('php-ps: FileMonitor');
        echo "FileMonitor Process: {$process->pid}, Scanning...\n";

        while (true) {
            $lastMtime = $this->lastMtime;
            foreach ($this->path as $path) {
                // recursive traversal directory
                $dir_iterator = new RecursiveDirectoryIterator($path);
                $iterator = new RecursiveIteratorIterator($dir_iterator);
                /** @var SplFileInfo $file */
                foreach ($iterator as $file) {
                    // only check php files
                    if (empty($this->ext)
                        || false === in_array(pathinfo((string) $file, PATHINFO_EXTENSION), $this->ext)
                    ) {
                        continue;
                    }
                    // check mtime
                    if ($lastMtime < $file->getMTime()) {
                        if ($this->lastMtime === 0) {
                            $lastMtime = $file->getMTime();
                        } else {
                            $lastMtime = $file->getMTime();
                            if (isset($ignoreFiles[(string) $file])) {
                                echo "[update] $file ignore...\n";
                                continue;
                            } else {
                                echo "[update] $file reload...\n";
                                $this->manager->getSwoole()->reload();
                                break 2;
                            }
                        }
                    }
                }
            }
            $this->lastMtime = $lastMtime;
            Coroutine::sleep($this->interval);
        }

        echo "Master process exited, I [{$process->pid}] also quit\n";
        return true;
    }
}
