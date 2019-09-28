<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Log;

use DateTime;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use think\console\Output;

class MonologConsoleHandler extends AbstractProcessingHandler
{
    private static $daemon = false;

    private $enable = false;

    private $levelStypeMap = [
        'DEBUG' => 'comment',
        'INFO' => 'info',
        'NOTICE' => null,
        'WARNING' => 'comment',
        'ERROR' => 'error',
        'CRITICAL' => null,
        'ALERT' => null,
        'EMERGENCY' => null,
    ];
    /**
     * @var Output
     */
    private $output;

    /**
     * 是否守护进程模式
     * @param bool $daemon
     */
    public static function setDaemon(bool $daemon)
    {
        self::$daemon = $daemon;
    }

    /**
     * @param bool $enable
     */
    public function setEnable(bool $enable): void
    {
        $this->enable = $enable;
    }

    public function __construct(Output $output, $level = Logger::DEBUG, $bubble = true)
    {
        $this->output = $output;
        parent::__construct($level, $bubble);
    }

    /**
     * Writes the record down to the log of the implementing handler
     *
     * @param array $record
     * @return void
     */
    protected function write(array $record): void
    {
        if (self::$daemon) {
            return;
        }
        if (!$this->enable) {
            return;
        }
        /** @var DateTime $datetime */
        ['message' => $message, 'level_name' => $levelName, 'datetime' => $datetime] = $record;

        if (isset($this->levelStypeMap[$levelName]) && null !== $this->levelStypeMap[$levelName]) {
            // <%></%>
            if (!preg_match('/<\/\S+>/', $message)) {
                $message = "<{$this->levelStypeMap[$levelName]}>{$message}</{$this->levelStypeMap[$levelName]}>";
            }
        }
        $levelName = str_pad($levelName, 5, ' ', STR_PAD_RIGHT);
        $message = "[{$datetime->format('Y-m-d\TH:i:s.uP')}] {$levelName} {$message}";
        $this->output->writeln($message);
    }
}
