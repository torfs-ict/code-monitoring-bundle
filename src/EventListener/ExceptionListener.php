<?php

declare(strict_types=1);

namespace TorfsICT\Bundle\CodeMonitoringBundle\EventListener;

use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
        if (!$this->enabled || $this->shouldIgnore($throwable)) {
            return;
        }

        $this->writer->exception($throwable);
    }

    private function shouldIgnore(\Throwable $throwable): bool
    {
        if ($throwable instanceof NotFoundHttpException) {
            if (str_contains($throwable->getMessage(), 'favicon.ico')) {
                return true;
            }
        }

        return false;
    }
}
