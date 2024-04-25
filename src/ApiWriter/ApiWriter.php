<?php

declare(strict_types=1);

namespace TorfsICT\Bundle\CodeMonitoringBundle\ApiWriter;

use Symfony\Component\Console\Debug\CliRequest;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use TorfsICT\Bundle\CodeMonitoringBundle\Exception\CaughtException;
use TorfsICT\Bundle\CodeMonitoringBundle\ExceptionRenderer\ExceptionRenderer;

final class ApiWriter
{
    private readonly string $url;

    private readonly bool $useSpool;

    /**
     * @var \SplObjectStorage<\Throwable, \Throwable>
     */
    private \SplObjectStorage $queue;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ExceptionRenderer $renderer,
        private readonly ?RequestStack $requestStack,
        private readonly ?Stopwatch $stopwatch,
        private readonly ?Profiler $profiler,
        private readonly string $endpoint,
        private readonly string $project,
        private readonly string $environment,
        private readonly string $secret,
        private readonly ?string $spool,
    ) {
        $this->url = sprintf('%s/monitoring', $this->endpoint);
        $this->useSpool = null !== $this->spool;
        $this->queue = new \SplObjectStorage();

        if ($this->useSpool && !is_dir((string) $this->spool)) {
            throw new \RuntimeException(sprintf('Spool directory "%s" does not exist.', $this->spool));
        }
    }

    public function exception(\Throwable $throwable): void
    {
        $this->queue->attach($throwable);
    }

    public function deprecation(\Throwable $throwable): void
    {
        $json = $this->toArray($throwable, false);
        $this->process('deprecation', $json);
    }

    /**
     * @param array<string, mixed> $json
     */
    private function process(string $type, array $json): void
    {
        $this->useSpool ? $this->queue($type, $json) : $this->post($this->url.'/'.$type, $json);
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(\Throwable $throwable, bool $includeDetails): array
    {
        $array = [
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'user' => $includeDetails ? $this->renderer->getUserIdentifier() : null,
            'message' => $throwable->getMessage(),
            'contents' => $this->renderer->render($throwable, $includeDetails),
        ];

        return $array;
    }

    /**
     * @param array<string, mixed> $json
     */
    private function queue(string $type, array $json): void
    {
        // Use a hash of the array to create the filename to prevent duplicate files
        $hash = hash('crc32', serialize($json));
        $path = sprintf('%s/spool.%s-%s', $this->spool, $type, $hash);
        if (!file_exists($path)) {
            file_put_contents($path, json_encode($json));
        }
    }

    /**
     * @param array<string, mixed> $json
     */
    private function post(string $url, array $json): void
    {
        $json = array_merge($json, [
            'project' => $this->project,
            'environment' => $this->environment,
            'secret' => $this->secret,
        ]);

        $this->httpClient->request('POST', $url, [
            'json' => $json,
            'headers' => [
                'Content-Type' => 'application/ld+json',
            ],
        ]);
    }

    public function sendSpool(): void
    {
        if (!$this->useSpool) {
            return;
        }

        assert(is_string($this->spool));

        foreach ((new \DirectoryIterator($this->spool)) as $file) {
            /** @var \DirectoryIterator $file */
            if (!$file->isFile()) {
                continue;
            }

            $basename = $file->getBasename();

            if (fnmatch('*.sending', $basename)) {
                continue;
            }

            $type = preg_filter('/spool\.([^-]+)-.+/', '$1', $basename);
            if (!in_array($type, ['deprecation', 'exception'], true)) {
                continue;
            }

            $seen = $file->getCTime();
            $renamed = $file->getPathname().'.sending';
            rename($file->getPathname(), $renamed);

            /** @var array<string, mixed> $json */
            $json = json_decode((string) file_get_contents($renamed), true);

            if (false !== $seen) {
                $seen = \DateTimeImmutable::createFromFormat('U', (string) $seen);
                if ($seen instanceof \DateTimeImmutable) {
                    $json['_seen'] = $seen->format(\DateTimeInterface::ATOM);
                }
            }

            $this->post($this->url.'/'.$type, $json);
            unlink($renamed);
        }
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        $this->onTerminate($event->getRequest(), $event->getResponse());
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        $request = $this->requestStack?->getCurrentRequest();
        if (!$request instanceof CliRequest || $request->command !== $event->getCommand()) {
            return;
        }

        if (null !== $sectionId = $request->attributes->get('_stopwatch_token')) {
            // we must close the section before saving the profile to allow late collect
            try {
                assert(is_string($sectionId));
                $this->stopwatch?->stopSection($sectionId);
            } catch (\LogicException) {
                // noop
            }
        }

        $request->command->exitCode = $event->getExitCode();
        $request->command->interruptedBySignal = $event->getInterruptingSignal();

        $this->onTerminate($request, $request->getResponse());
    }

    private function onTerminate(Request $request, Response $response): void
    {
        foreach ($this->queue as $throwable) {
            $json = $this->toArray($throwable, true);
            $json['caught'] = $throwable instanceof CaughtException;

            if (null !== $this->profiler) {
                $profile = $this->profiler->collect($request, $response, $throwable);
                if ($profile instanceof Profile) {
                    $this->profiler->saveProfile($profile);
                    $json['token'] = $profile->getToken();
                }
            }

            $this->process('exception', $json);
            $this->queue->detach($throwable);
        }
    }
}
