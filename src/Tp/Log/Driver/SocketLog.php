<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: luofei614 <weibo.com/luofei614>
// +----------------------------------------------------------------------

namespace HZEX\TpSwoole\Tp\Log\Driver;

use HZEX\TpSwoole\Task\SocketLogTask;
use think\App;
use think\contract\LogHandlerInterface;

/**
 * github: https://github.com/luofei614/SocketLog
 * @author luofei614<weibo.com/luofei614>
 */
class SocketLog implements LogHandlerInterface
{
    public $port = 1116; //SocketLog 服务的http的端口号

    protected $config = [
        // socket服务器地址
        'host'                => 'localhost',
        // 是否显示加载的文件列表
        'show_included_files' => false,
        // 日志强制记录到配置的client_id
        'force_client_ids'    => [],
        // 限制允许读取日志的client_id
        'allow_client_ids'    => [],
        //输出到浏览器默认展开的日志级别
        'expand_level'        => ['debug'],
    ];

    protected $css = [
        'sql'      => 'color:#009bb4;',
        'sql_warn' => 'color:#009bb4;font-size:14px;',
        'error'    => 'color:#f4006b;font-size:14px;',
        'page'     => 'color:#40e2ff;background:#171717;',
        'big'      => 'font-size:20px;color:red;',
    ];

    protected $allowForceClientIds = []; //配置强制推送且被授权的client_id

    protected $clientArg = [];

    /**
     * 架构函数
     * @access public
     * @param array $config 缓存参数
     */
    public function __construct(array $config = [])
    {
        if (!empty($config)) {
            $this->config = array_merge($this->config, $config);
        }
    }

    /**
     * @return App
     */
    private function getApp(): App
    {
        return App::getInstance();
    }

    /**
     * 调试输出接口
     * @access public
     * @param  array     $log 日志信息
     * @return bool
     */
    public function save(array $log): bool
    {
        if (!$this->check()) {
            return false;
        }

        $trace = [];

        $app = $this->getApp();
        if ($app->isDebug()) {
            $runtime    = round(microtime(true) - $app->getBeginTime(), 10);
            $reqs       = $runtime > 0 ? number_format(1 / $runtime, 2) : '∞';
            $time_str   = ' [运行时间：' . number_format($runtime, 6) . 's][吞吐率：' . $reqs . 'req/s]';
            $memory_use = number_format((memory_get_usage() - $app->getBeginMem()) / 1024, 2);
            $memory_str = ' [内存消耗：' . $memory_use . 'kb]';
            $file_load  = ' [文件加载：' . count(get_included_files()) . ']';

            if ($app->exists('request')) {
                $current_uri = $app->request->host(). $app->request->baseUrl();
            } else {
                $current_uri = 'cmd:' . implode(' ', $_SERVER['argv'] ?? ['unknown']);
            }

            // 基本信息
            $trace[] = [
                'type' => 'group',
                'msg'  => $current_uri . $time_str . $memory_str . $file_load,
                'css'  => $this->css['page'],
            ];
        }

        foreach ($log as $type => $val) {
            $trace[] = [
                'type' => in_array($type, $this->config['expand_level']) ? 'group' : 'groupCollapsed',
                'msg'  => '[ ' . $type . ' ]',
                'css'  => $this->css[$type] ?? '',
            ];

            foreach ($val as $msg) {
                if (!is_string($msg)) {
                    $msg = var_export($msg, true);
                }
                $trace[] = [
                    'type' => 'log',
                    'msg'  => $msg,
                    'css'  => '',
                ];
            }

            $trace[] = [
                'type' => 'groupEnd',
                'msg'  => '',
                'css'  => '',
            ];
        }

        if ($this->config['show_included_files']) {
            $trace[] = [
                'type' => 'groupCollapsed',
                'msg'  => '[ file ]',
                'css'  => '',
            ];

            $trace[] = [
                'type' => 'log',
                'msg'  => implode("\n", get_included_files()),
                'css'  => '',
            ];

            $trace[] = [
                'type' => 'groupEnd',
                'msg'  => '',
                'css'  => '',
            ];
        }

        $trace[] = [
            'type' => 'groupEnd',
            'msg'  => '',
            'css'  => '',
        ];

        $tabid = $this->getClientArg('tabid');

        if (!$client_id = $this->getClientArg('client_id')) {
            $client_id = '';
        }

        if (!empty($this->allowForceClientIds)) {
            //强制推送到多个client_id
            foreach ($this->allowForceClientIds as $force_client_id) {
                $client_id = $force_client_id;
                $this->sendToClient($tabid, $client_id, $trace, $force_client_id);
            }
        } else {
            $this->sendToClient($tabid, $client_id, $trace, '');
        }

        return true;
    }

    /**
     * 发送给指定客户端
     * @access protected
     * @author Zjmainstay
     * @param  $tabid
     * @param  $client_id
     * @param  $logs
     * @param  $force_client_id
     */
    protected function sendToClient($tabid, $client_id, $logs, $force_client_id)
    {
        $logs = [
            'tabid'           => $tabid,
            'client_id'       => $client_id,
            'logs'            => $logs,
            'force_client_id' => $force_client_id,
        ];

        $msg     = json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        $address = '/' . $client_id; //将client_id作为地址， server端通过地址判断将日志发布给谁

        $this->send($this->config['host'], $msg, $address);
    }

    protected function check()
    {
        $tabid = $this->getClientArg('tabid');

        //是否记录日志的检查
        if (!$tabid && !$this->config['force_client_ids']) {
            return false;
        }

        //用户认证
        $allow_client_ids = $this->config['allow_client_ids'];

        if (!empty($allow_client_ids)) {
            //通过数组交集得出授权强制推送的client_id
            $this->allowForceClientIds = array_intersect($allow_client_ids, $this->config['force_client_ids']);
            if (!$tabid && count($this->allowForceClientIds)) {
                return true;
            }

            $client_id = $this->getClientArg('client_id');
            if (!in_array($client_id, $allow_client_ids)) {
                return false;
            }
        } else {
            $this->allowForceClientIds = $this->config['force_client_ids'];
        }

        return true;
    }

    protected function getClientArg($name)
    {
        $app = $this->getApp();
        $key = 'HTTP_USER_AGENT';
        if (empty($app->request->server('HTTP_SOCKETLOG', ''))) {
            $key = 'HTTP_SOCKETLOG';
        }

        if (empty($socketLog = $app->request->server($key))) {
            return [];
        }

        if (empty($this->clientArg)) {
            if (!preg_match('/SocketLog\((.*?)\)/', $socketLog, $match)) {
                $this->clientArg = ['tabid' => null];
                return [];
            }
            parse_str($match[1] ?? '', $this->clientArg);
        }

        if (isset($this->clientArg[$name])) {
            return $this->clientArg[$name];
        }

        return [];
    }

    /**
     * @access protected
     * @param string $host    - $host of socket server
     * @param string $message - 发送的消息
     * @param string $address - 地址
     * @return bool
     */
    protected function send($host, $message = '', $address = '/')
    {
        $app = $this->getApp();
        if (false === $app->has('swoole.server') || 0 === $app->make('swoole.server')->manager_pid) {
            $url = 'http://' . $host . ':' . $this->port . $address;
            $ch  = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $headers = [
                "Content-Type: application/json;charset=UTF-8",
            ];
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); //设置header
            return curl_exec($ch);
        }

        SocketLogTask::push($host, $this->port, $address, $message);
        return true;
    }
}
