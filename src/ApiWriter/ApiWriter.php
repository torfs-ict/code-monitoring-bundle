<?php

declare(strict_types=1);

namespace TorfsICT\Bundle\CodeMonitoringBundle\ApiWriter;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use TorfsICT\Bundle\CodeMonitoringBundle\Exception\CaughtException;
use TorfsICT\Bundle\CodeMonitoringBundle\ExceptionRenderer\ExceptionRenderer;

final readonly class ApiWriter
{
    private string $url;
    private bool $useSpool;

    public function __construct(
        private HttpClientInterface $httpClient,
        private ExceptionRenderer $renderer,
        private string $endpoint,
        private string $project,
        private string $environment,
        private string $secret,
        private ?string $spool,
    ) {
        $this->url = sprintf('%s/monitoring', $this->endpoint);

        $this->useSpool = null !== $this->spool;

        if ($this->useSpool && !is_dir((string) $this->spool)) {
            throw new \RuntimeException(sprintf('Spool directory "%s" does not exist.', $this->spool));
        }
    }

    public function exception(\Throwable $throwable): void
    {
        $json = $this->toArray($throwable);
        $json['caught'] = $throwable instanceof CaughtException;
        $this->process('exception', $json);
    }

    public function deprecation(\Throwable $throwable): void
    {
        $json = $this->toArray($throwable);
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
    private function toArray(\Throwable $throwable): array
    {
        return [
            'project' => $this->project,
            'environment' => $this->environment,
            'secret' => $this->secret,
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'message' => $throwable->getMessage(),
            'contents' => $this->renderer->render($throwable),
        ];
    }

    /**
     * @param array<string, mixed> $json
     */
    private function queue(string $type, array $json): void
    {
        // Use a hash of the array to create the filename to prevent duplicate files
        $hash = hash('crc32', serialize($json));
        $path = sprintf('%s/spool.%s-%s', $this->spool, $type, $hash);
        file_put_contents($path, json_encode($json));
    }

    /**
     * @param array<string, mixed> $json
     */
    private function post(string $url, array $json): void
    {
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

            $renamed = $file->getPathname().'.sending';
            rename($file->getPathname(), $renamed);

            /** @var array<string, mixed> $json */
            $json = json_decode((string) file_get_contents($renamed), true);
            $this->post($this->url.'/'.$type, $json);
            unlink($renamed);
        }
    }
}
