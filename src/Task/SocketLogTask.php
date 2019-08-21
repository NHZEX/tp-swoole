<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Task;

use Swoole\Coroutine\Http\Client;

class SocketLogTask extends TaskAbstract
{
    public static function push($host, $port, $address, $message)
    {
        self::delivery([
            'host' => $host,
            'port' => $port,
            'address' => $address,
            'message' => $message,
        ]);
    }

    public function handle($arge)
    {
        ['host' => $host, 'port' => $port, 'address' => $address, 'message' => $message] = $arge;
        $cli = new Client($host, $port);
        $cli->setMethod('POST');
        $cli->setHeaders([
            'Host' => $host,
            'Content-Type' => 'application/json;charset=UTF-8',
            'Accept' => 'text/html,application/xhtml+xml,application/xml',
            'Accept-Encoding' => 'gzip',
        ]);
        $cli->set(['timeout' => 3, 'keep_alive' => true]);
        $cli->post($address, $message);
        return $cli->statusCode;
    }
}
