<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Concerns;

use HZEX\TpSwoole\PidManager;
use Psr\Log\LoggerInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http2\Request as H2Request;
use Swoole\Http2\Response as H2Response;
use unzxin\zswCore\Event;

/**
 * Class InteractsWithHttp
 * @package HZEX\TpSwoole\Concerns
 * @method Event getEvent()
 * @property PidManager $pidManager
 * @property LoggerInterface $logger
 */
trait InteractsWithHttp
{
    /**
     * 请求到达回调（Http）
     * @param Request|H2Request   $request
     * @param Response|H2Response $response
     */
    public function onRequest(Request $request, Response $response): void
    {
        // 事件触发
        $this->getEvent()->trigSwooleRequest(func_get_args());
    }
}
