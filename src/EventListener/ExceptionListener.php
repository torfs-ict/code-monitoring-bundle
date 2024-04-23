<?php

declare(strict_types=1);

namespace TorfsICT\Bundle\CodeMonitoringBundle\EventListener;

use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\User\UserInterface;
use TorfsICT\Bundle\CodeMonitoringBundle\ApiWriter\ApiWriter;
use TorfsICT\Bundle\CodeMonitoringBundle\Exception\CaughtException;

readonly class ExceptionListener
{
    private string $directory;
    private bool $enabled;

    public function __construct(
        private TokenStorageInterface $tokenStorage,
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
        $caught = '';
        if ($throwable instanceof CaughtException) {
            $throwable = $throwable->getCaughtException();
            $caught = 'caught_';
        }
        $path = sprintf('%s/%s%s%s.log', $this->directory, $caught, $name[1], mb_substr($name[0], 1));

        $contents = $throwable->getMessage()."\n\n".
            $throwable->getTraceAsString()."\n\n".
            $this->formatBacktrace(debug_backtrace());

        file_put_contents($path, $contents);
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

    /**
     * @param array<int, mixed[]> $backtrace
     */
    private function formatBacktrace(array $backtrace): string
    {
        $string = '';

        if (PHP_SAPI !== 'cli') {
            $string .= "\nREQUEST DETAILS\n".$this->getRequestDetails();
        } else {
            $string .= "\nCOMMAND DETAILS\n".$this->getCommandDetails();
        }

        $string .= "\nFULL BACKTRACE\n";

        foreach ($backtrace as $depth => $trace) {
            $class = !array_key_exists('class', $trace) ? '' : $trace['class'];
            $type = !array_key_exists('type', $trace) ? '' : $trace['type'];
            $function = !array_key_exists('function', $trace) ? '' : $trace['function'];
            $file = !array_key_exists('file', $trace) ? '' : $trace['file'];
            $line = !array_key_exists('line', $trace) ? '' : $trace['line'];
            $args = !array_key_exists('args', $trace) ? '' : $this->argsToString($trace['args']);

            $string .= "#{$depth}: {$class}{$type}{$function}({$args}) called at [{$file}:{$line}]\n";
        }

        return $string;
    }

    /**
     * @param mixed[] $args
     */
    private function argsToString(array $args): string
    {
        static $nestingLevel = 1;
        $string = '';
        foreach ($args as $arg) {
            if ('' != $string) {
                $string .= ', ';
            }

            if (is_object($arg)) {
                $string .= get_class($arg);
            } elseif (is_array($arg)) {
                if ($nestingLevel >= 2) {
                    $string .= '[ ... ]';
                } else {
                    ++$nestingLevel;
                    $string .= '['.$this->argsToString($arg).']';
                }
            } else {
                $string .= "'".filter_var($arg, FILTER_SANITIZE_ADD_SLASHES)."'";
            }
        }

        return $string;
    }

    private function getRequestDetails(): string
    {
        $accessor = new PropertyAccessor();

        $user = null;
        $token = $this->tokenStorage->getToken();
        if (null !== $token) {
            $user = $token->getUser();
        }

        if ($user instanceof UserInterface) {
            $userData = [];

            if ($accessor->isReadable($user, 'id')) {
                $userData['id'] = $accessor->getValue($user, 'id');
            }

            $userData['identifier'] = $user->getUserIdentifier();

            if ($token instanceof SwitchUserToken) {
                $impersonator = $token->getOriginalToken()->getUser();
                if ($impersonator instanceof UserInterface) {
                    $userData['impersonator'] = $impersonator->getUserIdentifier();
                }
            }
        } else {
            $userData = null;
        }

        $details = '$_USER: '.$this->json($userData)."\n";
        $details .= '$_GET: '.$this->json($_GET)."\n";
        $details .= '$_POST: '.$this->json($_POST)."\n";
        $details .= '$_COOKIE: '.$this->json($_COOKIE)."\n";
        // $details .= '$_ENV: '.$this->json($_ENV ?? null)."\n";
        $details .= '$_FILES: '.$this->json($_FILES)."\n";
        $details .= '$_REQUEST: '.$this->json($_REQUEST)."\n";
        $details .= '$_SERVER: '.$this->json($_SERVER)."\n";
        $details .= '$_SESSION: '.$this->json($_SESSION ?? null)."\n";

        return $details;
    }

    private function getCommandDetails(): string
    {
        global $argv;

        return '$argv: '.var_export($argv, true)."\n";
    }

    /**
     * @param mixed[] $array
     */
    private function json(?array $array): string
    {
        return (string) json_encode($array, JSON_PRETTY_PRINT);
    }
}
