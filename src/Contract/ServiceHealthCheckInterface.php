<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Contract;

interface ServiceHealthCheckInterface
{
    /**
     * 健康检查结果
     * @return string
     */
    public function getMessage(): string;

    /**
     * 健康检查错误码
     * @return int
     */
    public function getCode(): int;

    /**
     * 健康检查处理器
     * @return bool
     */
    public function handle(): bool;
}
