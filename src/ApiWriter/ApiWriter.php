<?php

declare(strict_types=1);

namespace TorfsICT\Bundle\CodeMonitoringBundle\ApiWriter;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ApiWriter
{
    private string $url;
    private bool $useSpool;

    public function __construct(
        private HttpClientInterface $httpClient,
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

    public function exception(string $title, string $contents, bool $caught): void
    {
        $json = [
            'project' => $this->project,
            'environment' => $this->environment,
            'secret' => $this->secret,
            'title' => $title,
            'contents' => $contents,
            'caught' => $caught,
        ];

        $this->useSpool ? $this->queue('exception', $json) : $this->post($this->url.'/exception', $json);
    }

    public function deprecation(string $file, int $line, string $message, string $contents): void
    {
        $json = [
            'project' => $this->project,
            'environment' => $this->environment,
            'secret' => $this->secret,
            'file' => $file,
            'line' => $line,
            'message' => $message,
            'contents' => $contents,
        ];

        $this->useSpool ? $this->queue('deprecation', $json) : $this->post($this->url.'/deprecation', $json);
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
