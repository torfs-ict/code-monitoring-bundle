<?php

declare(strict_types=1);

namespace TorfsICT\Bundle\CodeMonitoringBundle\ApiWriter;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ApiWriter
{
    private string $url;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $endpoint,
        private string $project,
        private string $environment,
        private string $secret,
    ) {
        $this->url = sprintf('%s/monitoring/exception', $this->endpoint);
    }

    public function exception(string $title, string $contents, bool $caught): void
    {
        $this->httpClient->request('POST', $this->url, [
            'json' => [
                'project' => $this->project,
                'environment' => $this->environment,
                'secret' => $this->secret,
                'title' => $title,
                'contents' => $contents,
                'caught' => $caught,
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
            ],
        ]);
    }
}
