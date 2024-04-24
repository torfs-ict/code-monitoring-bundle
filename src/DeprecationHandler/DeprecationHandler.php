<?php

declare(strict_types=1);

namespace TorfsICT\Bundle\CodeMonitoringBundle\DeprecationHandler;

use Monolog\Handler\Handler;
use Monolog\LogRecord;
use TorfsICT\Bundle\CodeMonitoringBundle\ApiWriter\ApiWriter;
use TorfsICT\Bundle\CodeMonitoringBundle\ExceptionRenderer\ExceptionRenderer;

final class DeprecationHandler extends Handler
{
    private bool $enabled;

    public function __construct(
        private readonly ApiWriter $writer,
        private readonly ExceptionRenderer $renderer,
        string $endpoint,
    ) {
        $this->enabled = !empty($endpoint);
    }

    /**
     * @param array<string, mixed>|LogRecord $record
     */
    public function isHandling($record): bool
    {
        return true;
    }

    /**
     * @param array{context: array{exception?: \Throwable}}|LogRecord $record
     */
    public function handle($record): bool
    {
        if (!$this->enabled) {
            return false;
        }

        if ($record instanceof LogRecord) {
            $exception = $record->context['exception'];
        } elseif (is_array($record) && array_key_exists('exception', $record['context'])) {
            $exception = $record['context']['exception'];
        } else {
            $exception = null;
        }

        if ($exception instanceof \Throwable) {
            $contents = $this->renderer->render($exception);

            $this->writer->deprecation(
                $exception->getFile(),
                $exception->getLine(),
                substr($exception->getMessage(), 0, 255),
                $contents,
            );

            return true;
        }

        return false;
    }
}
