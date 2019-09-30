<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Log;

use Monolog\ErrorHandler;
use Monolog\Utils;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use function HuangZx\ref_get_prop;

class MonologErrorHandler extends ErrorHandler
{
    public function handleException($e)
    {
        $level = LogLevel::ERROR;

        /** @noinspection PhpUnhandledExceptionInspection */
        $refUncaughtExceptionLevelMap = ref_get_prop($this, 'uncaughtExceptionLevelMap');
        foreach ($refUncaughtExceptionLevelMap->getValue() as $class => $candidate) {
            if ($e instanceof $class) {
                $level = $candidate;
                break;
            }
        }

        /** @noinspection PhpUnhandledExceptionInspection */
        $refLogger = ref_get_prop($this, 'logger');
        /** @var LoggerInterface $logger */
        $logger = $refLogger->getValue();
        $logger->log(
            $level,
            sprintf(
                'Uncaught Exception %s: "%s" at %s line %s',
                Utils::getClass($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ),
            ['exception' => $e]
        );

        /** @noinspection PhpUnhandledExceptionInspection */
        $refPreviousExceptionHandler = ref_get_prop($this, 'previousExceptionHandler');
        $previousExceptionHandler = $refPreviousExceptionHandler->getValue();
        if ($previousExceptionHandler) {
            call_user_func($previousExceptionHandler, $e);
        }
    }
}
