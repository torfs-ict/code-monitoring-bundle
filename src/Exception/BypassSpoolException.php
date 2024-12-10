<?php

namespace TorfsICT\Bundle\CodeMonitoringBundle\Exception;

final class BypassSpoolException extends \Exception
{
    public function __construct(private readonly \Throwable $exception)
    {
        parent::__construct($this->exception->getMessage(), $this->exception->getCode(), $this->exception);
    }

    public function getException(): \Throwable
    {
        return $this->exception;
    }
}
