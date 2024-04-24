<?php

declare(strict_types=1);

namespace TorfsICT\Bundle\CodeMonitoringBundle\ExceptionRenderer;

use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class ExceptionRenderer
{
    public function __construct(private TokenStorageInterface $tokenStorage)
    {
    }

    public function render(\Throwable $throwable): string
    {
        return $throwable->getMessage()."\n\n".
            $throwable->getTraceAsString()."\n\n".
            $this->formatBacktrace(debug_backtrace());
    }

    public function getUserIdentifier(): ?string
    {
        $user = null;

        $token = $this->tokenStorage->getToken();
        if (null !== $token) {
            $user = $token->getUser();
            if ($user instanceof UserInterface) {
                return $user->getUserIdentifier();
            }
        }

        return $user;
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
