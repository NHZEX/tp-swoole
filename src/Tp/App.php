<?php

namespace HZEX\TpSwoole\Tp;

use Exception;
use HZEX\TpSwoole\Event;
use HZEX\TpSwoole\Service;
use Swoole\Http\Request;
use think\Db;
use think\Error;
use think\exception\HttpException;
use think\Response;
use Throwable;

/**
 * Class App
 * @package app\Swoole\Tp
 * @property Cookie $cookie
 * @property Event  $event
 */
class App extends \think\App
{
    public function __construct($appPath = '')
    {
        $that = self::getInstance();
        // 尝试获取应用路径
        if ($that->exists(\think\App::class)) {
            $appPath = $that->getAppPath();
        }

        // 尝试恢复服务绑定
        if ($that->exists(Service::class)) {
            $this->bind = array_merge($this->bind, Service::getBind());
        }

        parent::__construct($appPath);
    }

    /**
     * App 值重设
     */
    public function reset()
    {
        // 重置应用的开始时间和内存占用
        $this->beginTime = microtime(true);
        $this->beginMem  = memory_get_usage();
        // 重置数据库查询次数
        Db::$queryTimes = 0;
        // 重置数据库执行次数
        Db::$executeTimes = 0;
    }

    /**
     * 处理Swoole请求
     * @param Request  $request
     * @return Response
     * @throws Throwable
     */
    public function runSwoole(Request $request)
    {
        try {
            $this->reset();

            $header  = $request->header ?: [];
            $server  = $request->server ?: [];

            if (isset($header['x-requested-with'])) {
                $server['HTTP_X_REQUESTED_WITH'] = $header['x-requested-with'];
            }
            if (isset($header['referer'])) {
                $server['http_referer'] = $header['referer'];
            }
            if (isset($header['host'])) {
                $server['http_host'] = $header['host'];
            }
            if (isset($req->get[$this->config->get('var_pathinfo')])) {
                $server['path_info'] = $request->get[$this->config->get('var_pathinfo')];
            }

            // 重新实例化请求对象 处理swoole请求数据
            $queryStr = !empty($request->server['query_string']) ? ('&' . $request->server['query_string']) : '';
            $this->request->withHeader($header)
                ->withServer($server)
                ->withGet($request->get ?: [])
                ->withPost($request->post ?: [])
                ->withCookie($request->cookie ?: [])
                ->withInput($request->rawContent())
                ->withFiles($request->files ?: [])
                ->setBaseUrl($request->server['request_uri'])
                ->setUrl($request->server['request_uri'] . $queryStr)
                ->setHost($request->header['host'])
                ->setPathinfo(ltrim($request->server['path_info'], '/'));

            // 更新请求对象实例
            $this->route->setRequest($this->request);

            // 重新加载全局中间件
            $this->middleware->import((array) $this->make('middleware.global.file'));

            ob_start();
            $resp = $this->run();
            $resp->send();
            $content = ob_get_clean();
            $content = ob_get_contents() . $content;

            // Trace调试注入
            if ($this->env->get('app_trace', $this->config->get('app_trace'))) {
                $this->debug->inject($resp, $content);
            }

            $resp->content($content);
        } catch (HttpException $e) {
            $resp = $this->exception($e);
        } catch (Exception $e) {
            $resp = $this->exception($e);
        } catch (Throwable $e) {
            $resp = $this->exception($e);
        }

        return $resp;
    }

    /**
     * @param Throwable $e
     * @return Response
     * @throws Throwable
     */
    protected function exception(Throwable $e)
    {
        if ($e instanceof Exception) {
            $handler = Error::getExceptionHandler();
            $handler->report($e);

            return $handler->render($e);
        }

        throw $e;
    }
}
