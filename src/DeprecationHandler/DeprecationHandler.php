<?php

declare(strict_types=1);

namespace TorfsICT\Bundle\CodeMonitoringBundle\DeprecationHandler;

use Monolog\Handler\Handler;
use Monolog\LogRecord;
use TorfsICT\Bundle\CodeMonitoringBundle\ApiWriter\ApiWriter;

final class DeprecationHandler extends Handler
{
    private bool $enabled;

    public function __construct(private readonly ApiWriter $writer, string $endpoint)
    {
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
            $this->writer->deprecation($exception);

            return true;
        }

        return false;
    }
}
