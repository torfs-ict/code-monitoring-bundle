<?php

declare(strict_types=1);

namespace TorfsICT\Bundle\CodeMonitoringBundle\EventListener;

use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use TorfsICT\Bundle\CodeMonitoringBundle\ApiWriter\ApiWriter;
use TorfsICT\Bundle\CodeMonitoringBundle\Exception\CaughtException;
use TorfsICT\Bundle\CodeMonitoringBundle\ExceptionRenderer\ExceptionRenderer;

readonly class ExceptionListener
{
    private string $directory;
    private bool $enabled;

    public function __construct(
        private ExceptionRenderer $renderer,
        private ApiWriter $writer,
        string $logDir,
        string $endpoint,
    ) {
        $dirname = sprintf('%s/exceptions', $logDir);
        if (!is_dir($dirname)) {
            mkdir($dirname, 0755, true);
        }
        $this->directory = $dirname;

        $this->enabled = !empty($endpoint);
    }

    public function getLogDirectory(): string
    {
        return $this->directory;
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

        $name = explode(' ', microtime());
        $contents = $this->renderer->render($throwable);

        $this->writer->exception($throwable->getMessage(), $contents, '' !== $caught);
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
