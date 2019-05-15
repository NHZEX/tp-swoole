<?php

namespace HZEX\TpSwoole\Tp;

class Log extends \think\Log
{
    /**
     * 记录日志信息
     * @access public
     * @param  mixed  $msg       日志信息
     * @param  string $type      日志级别
     * @param  array  $context   替换内容
     * @return $this
     */
    public function record($msg, $type = 'info', array $context = [])
    {
        if (!$this->allowWrite) {
            return $this;
        }

        if (is_string($msg) && !empty($context)) {
            $replace = [];
            foreach ($context as $key => $val) {
                $replace['{' . $key . '}'] = $val;
            }

            $msg = strtr($msg, $replace);
        }

        $this->log[$type][] = $msg;

        return $this;
    }
}
