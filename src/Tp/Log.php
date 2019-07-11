<?php

namespace HZEX\TpSwoole\Tp;

class Log extends \think\Log
{
    protected $isSwoole = false;

    /**
     * 初始化
     * @access public
     * @param array $config
     */
    public function init(array $config = [])
    {
        parent::init($config);

        $this->isSwoole = exist_swoole();
    }

    /**
     * 记录通道日志
     * @access public
     * @param  string $channel 日志通道
     * @param  mixed  $msg  日志信息
     * @param  string $type 日志级别
     * @return void
     */
    protected function channelLog(string $channel, $msg, string $type): void
    {
        if (!empty($this->close['*']) || !empty($this->close[$channel])) {
            return;
        }

        if (!$this->isSwoole && ($this->isCli || !empty($this->config['channels'][$channel]['realtime_write']))) {
            // 实时写入
            $this->write($msg, $type, true, $channel);
        } else {
            $this->log[$channel][$type][] = $msg;
        }
    }
}
