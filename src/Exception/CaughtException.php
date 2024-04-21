<?php

declare(strict_types=1);

namespace TorfsICT\Bundle\CodeMonitoringBundle\Exception;

class CaughtException extends \Exception
{
    public function __construct(private readonly \Throwable $exception)
    {
        parent::__construct($exception->getMessage(), $exception->getCode(), $exception);
    }

    public function getCaughtException(): \Throwable
    {
        return $this->exception;
    }
}
