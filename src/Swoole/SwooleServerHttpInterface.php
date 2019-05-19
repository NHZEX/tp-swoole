<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Swoole;

use Swoole\Http\Request;
use Swoole\Http2\Request as H2Request;
use Swoole\Http\Response;
use Swoole\Http2\Response as H2Response;

interface SwooleServerHttpInterface
{
    /**
     * 请求到达回调（Http）
     * @param Request|H2Request  $request
     * @param Response|H2Response $response
     */
    public function onRequest(Request $request, Response $response): void;
}
