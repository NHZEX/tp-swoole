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
namespace HZEX\TpSwoole\Tp;

use Swoole\Http\Response;
use think\Cookie as BaseCookie;

/**
 * Swoole Cookie类
 */
class Cookie extends BaseCookie
{
    /** @var Response */
    protected $response;

    /**
     * Cookie初始化
     * @access public
     * @param  array $config
     * @return void
     */
    public function init(array $config = [])
    {
        $this->config = array_merge($this->config, array_change_key_case($config));
    }

    /**
     * 设置Swoole响应对象
     * @param Response $response
     */
    public function setResponse(Response $response)
    {
        $this->response = $response;
    }

    /**
     * Cookie 设置保存
     *
     * @access public
     * @param string $name   cookie名称
     * @param mixed  $value  cookie值
     * @param        $expire
     * @param array  $option 可选参数
     * @return void
     */
    protected function setCookie($name, $value, $expire, $option = [])
    {
        $this->response->cookie(
            $name,
            $value,
            $expire,
            $option['path'],
            $option['domain'],
            $option['secure'],
            $option['httponly']
        );
    }
}
