<?php

namespace HZEX\TpSwoole\Tp;

use Exception;
use Swoole\Http\Request;
use Swoole\Http\Response;
use think\Db;
use think\Error;
use think\exception\HttpException;
use Throwable;

/**
 * Class App
 * @package app\Swoole\Tp
 * @property Cookie $cookie
 */
class App extends \think\App
{
    /**
     * 处理Swoole请求
     * @param Request  $request
     * @param Response $response
     * @return void
     * @throws Throwable
     */
    public function swoole(Request $request, Response $response)
    {
        try {
            // 重置应用的开始时间和内存占用
            $this->beginTime = microtime(true);
            $this->beginMem  = memory_get_usage();

            // 重置数据库查询次数
            Db::$queryTimes = 0;

            // 重置数据库执行次数
            Db::$executeTimes = 0;

            // 销毁当前请求对象实例
            $this->delete(\think\Request::class);
            $this->delete(\think\Cookie::class);

            // 设置Cookie类Response
            $this->cookie->setResponse($response);

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

            $_SERVER = array_change_key_case($server, CASE_UPPER);

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
            if (is_file($this->appPath . 'middleware.php')) {
                /** @noinspection PhpIncludeInspection */
                $middleware = include $this->appPath . 'middleware.php';
                if (is_array($middleware)) {
                    $this->middleware->import($middleware);
                }
            }

            ob_start();
            $resp = $this->run();
            $resp->send();
            $content = ob_get_clean();
            $status  = $resp->getCode();

            // Trace调试注入
            if ($this->env->get('app_trace', $this->config->get('app_trace'))) {
                $this->debug->inject($resp, $content);
            }

            // 清除中间件数据
            $this->middleware->clear();

            // 发送状态码
            $response->status($status);

            // 发送Header
            foreach ($resp->getHeader() as $key => $val) {
                $response->header($key, $val);
            }

            if (false === empty($content)) {
                $size = 524288; // 单次发送长度 512K
                $chunk = ceil(strlen($content) / $size);
                for ($i = 0; $i < $chunk; $i++) {
                    $response->write(substr($content, $i * $size, $size));
                }
                $response->end();
            } else {
                $response->end('');
            }
        } catch (HttpException $e) {
            $this->exception($response, $e);
        } catch (Exception $e) {
            $this->exception($response, $e);
        } catch (Throwable $e) {
            $this->exception($response, $e);
        }
    }

    /**
     * @param Response   $response
     * @param Throwable $e
     * @throws Throwable
     */
    protected function exception(Response $response, Throwable $e)
    {
        if ($e instanceof Exception) {
            $handler = Error::getExceptionHandler();
            $handler->report($e);

            $resp    = $handler->render($e);
            $content = $resp->getContent();
            $code    = $resp->getCode();

            $response->status($code);
            $response->end($content);
        } else {
            $response->status(500);
            $response->end($e->getMessage());
        }

        throw $e;
    }
}
