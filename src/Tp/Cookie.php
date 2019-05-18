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

use ReflectionClass;
use ReflectionException;
use think\Container;
use think\Cookie as BaseCookie;
use think\Request;

/**
 * Swoole Cookie类
 */
class Cookie extends BaseCookie
{
    /** @var array */
    protected $cookie = [];

    /** @var Request */
    protected $requestCookie = [];

    /**
     * Cookie初始化
     * @access public
     * @param array $config
     * @return void
     * @throws ReflectionException
     */
    public function init(array $config = [])
    {
        $this->config = array_merge($this->config, array_change_key_case($config));
        $request = Container::get('request');
        $ref = new ReflectionClass($request);
        $val = $ref->getProperty('cookie');
        $val->setAccessible(true);
        $this->requestCookie = $val->getValue($request) ?: [];

    }

    /**
     * @return array
     */
    public function getCookie()
    {
        return $this->cookie;
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
        $this->cookie[$name] = [$value, $expire, $option,];
    }

    /**
     * Cookie 设置、获取、删除
     *
     * @access public
     * @param  string $name  cookie名称
     * @param  mixed  $value cookie值
     * @param  mixed  $option 可选参数 可能会是 null|integer|string
     * @return void
     */
    public function set($name, $value = '', $option = null)
    {
        // 参数设置(会覆盖黙认设置)
        if (!is_null($option)) {
            if (is_numeric($option)) {
                $option = ['expire' => $option];
            } elseif (is_string($option)) {
                parse_str($option, $option);
            }

            $config = array_merge($this->config, array_change_key_case($option));
        } else {
            $config = $this->config;
        }

        // 设置cookie
        if (is_array($value)) {
            array_walk_recursive($value, [$this, 'jsonFormatProtect'], 'encode');
            $value = 'think:' . json_encode($value);
        }

        $expire = !empty($config['expire']) ? time() + intval($config['expire']) : 0;

        $this->setCookie($name, $value, $expire, $config);
    }

    /**
     * 判断Cookie数据
     * @access public
     * @param  string        $name cookie名称
     * @param  string|null   $prefix cookie前缀
     * @return bool
     */
    public function has($name, $prefix = null)
    {
        return isset($this->requestCookie[$name]);
    }

    /**
     * Cookie获取
     * @access public
     * @param  string        $name cookie名称 留空获取全部
     * @param  string|null   $prefix cookie前缀
     * @return mixed
     */
    public function get($name = '', $prefix = null)
    {
        return $this->requestCookie[$name] ?? null;
    }

    /**
     * Cookie删除
     * @access public
     * @param  string        $name cookie名称
     * @param  string|null   $prefix cookie前缀
     * @return void
     */
    public function delete($name, $prefix = null)
    {
        $this->setCookie($name, '', time() - 3600, $this->config);
    }

    /**
     * Cookie清空
     * @access public
     * @param  string|null $prefix cookie前缀
     * @return void
     */
    public function clear($prefix = null)
    {
        $cookie = $this->get();
        if (is_array($cookie)) {
            foreach ($this->get() as $key => $val) {
                $this->setcookie($key, '', time() - 3600, $this->config);
            }
        }

        return;
    }
}
