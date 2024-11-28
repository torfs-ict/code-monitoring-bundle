<?php

declare(strict_types=1);

namespace TorfsICT\Bundle\CodeMonitoringBundle\EventListener;

use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use TorfsICT\Bundle\CodeMonitoringBundle\ApiWriter\ApiWriter;

readonly class ExceptionListener
{
    private bool $enabled;

    public function __construct(private ApiWriter $writer, string $endpoint)
    {
        $this->enabled = !empty($endpoint);
    }

    public function http(ExceptionEvent $event): void
    {
        $this->log($event->getThrowable());
    }

    public function cli(ConsoleErrorEvent $event): void
    {
        $this->log($event->getError());
    }

    public function log(\Throwable $throwable): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->writer->exception($throwable);
    }
}
